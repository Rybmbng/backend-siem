<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AgentController extends Controller
{
    private $chUrl;
    private $chAuth;

    public function __construct()
    {
        $host = env('CLICKHOUSE_HOST', '127.0.0.1');
        $port = env('CLICKHOUSE_PORT', '8123');
        $this->chUrl  = "http://{$host}:{$port}/";
        
        $this->chAuth = [
            'X-ClickHouse-User' => env('CLICKHOUSE_USERNAME', 'default'),
            'X-ClickHouse-Key'  => env('CLICKHOUSE_PASSWORD', ''),
        ];
    }

    public function dashboard()
    {
        return view('dashboard');
    }
    public function register(Request $request)
    {
        $agentName = $request->agent_name ?? 'unknown';
        // Cek apakah agent sudah ada biar API Key tidak berubah terus
        $existing = DB::table('agents')->where('hostname', $agentName)->first();

        if ($existing) {
            // Update IP & Last Seen saja
            DB::table('agents')->where('id', $existing->id)->update([
                'ip_address'     => $request->ip(),
                'last_heartbeat' => now(),
                'updated_at'     => now(),
            ]);
            return response()->json(['status' => 'success', 'api_key' => $existing->api_key]);
        }

        // Kalau baru, buat baru
        $apiKey = \Illuminate\Support\Str::random(40);
        try {
            DB::table('agents')->insert([
                'hostname'       => $agentName,
                'api_key'        => $apiKey,
                'ip_address'     => $request->ip(),
                'os_type'        => 'linux',
                'status'         => 'active',
                'last_heartbeat' => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            return response()->json(['status' => 'success', 'api_key' => $apiKey]);
        } catch (\Exception $e) {
            Log::error("Agent Registration Failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Database error'], 500);
        }
    }

    /**
     * 2. TERIMA METRICS (CPU/RAM) - INI YANG BARU
     */
    public function storeMetrics(Request $request)
    {
        // Validasi API Key
        $apiKey = $request->header('X-API-KEY');
        $agent  = DB::table('agents')->where('api_key', $apiKey)->first();

        if (!$agent) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        try {
            // Simpan ke MySQL (server_metrics)
            DB::table('server_metrics')->insert([
                'agent_id'   => $agent->id,
                'cpu_usage'  => $request->input('cpu_usage', 0),
                'ram_usage'  => $request->input('ram_usage', 0),
                'created_at' => now(),
            ]);
            
            // Sekalian Update Heartbeat (Tanda Agent Hidup)
            DB::table('agents')->where('id', $agent->id)->update([
                'last_heartbeat' => now(),
                'ip_address'     => $request->ip() // Update IP jaga-jaga berubah
            ]);

            return response()->json(['status' => 'saved']);
        } catch (\Exception $e) {
            Log::error("Store Metrics Error: " . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * 3. Data Dashboard (Stats + Agent List + CPU/RAM Terkini)
     */
    public function getDashboardStats()
    {
        try {
            // A. Ambil Stats Global dari ClickHouse
            $query = "SELECT count() as total_logs, countIf(message LIKE '%Failed%' OR message LIKE '%error%') as attacks FROM siem_logs.security_logs FORMAT JSON";
            $stats = ['total_logs' => 0, 'attacks' => 0];
            
            try {
                $response = Http::withHeaders($this->chAuth)
                    ->timeout(2) // Timeout cepat biar dashboard gak loading lama
                    ->withBody($query, 'text/plain')
                    ->post($this->chUrl);

                if ($response->successful()) {
                    $chData = $response->json();
                    $stats = $chData['data'][0] ?? $stats;
                }
            } catch (\Exception $e) {
                // Ignore clickhouse error for stats, continue to load agents
            }

            // B. Ambil Data Agent + Metric Terakhir dari MySQL
            $agents = DB::table('agents')
                ->select('id', 'hostname', 'last_heartbeat', 'ip_address')
                ->get()
                ->map(function($agent) {
                    // Ambil Metric Terakhir Agent Ini
                    $metric = DB::table('server_metrics')
                                ->where('agent_id', $agent->id)
                                ->orderBy('created_at', 'desc')
                                ->first();

                    // Cek Status Online
                    $isOnline = false;
                    $lastSeenStr = 'Never';

                    if ($agent->last_heartbeat) {
                        $lastSeen = Carbon::parse($agent->last_heartbeat);
                        $isOnline = $lastSeen->diffInSeconds(now()) < 45; 
                        $lastSeenStr = $lastSeen->diffForHumans();
                    }

                    return [
                        'hostname'  => $agent->hostname,
                        'ip'        => $agent->ip_address,
                        'is_online' => $isOnline,
                        'last_seen' => $lastSeenStr,
                        
                        'cpu'       => $metric->cpu_usage ?? 0,
                        'ram'       => $metric->ram_usage ?? 0
                    ];
                });

            return response()->json([
                'stats'        => $stats,
                'agents'       => $agents,
                'active_count' => $agents->where('is_online', true)->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Stats Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function storeLog(Request $request)
    {
        $apiKey = $request->header('X-API-KEY');
        $agent  = DB::table('agents')->where('api_key', $apiKey)->first();

        if (!$agent) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $message = str_replace("'", "''", $request->message);
        $severity = $request->severity ?? 'info';
        $logType = $request->log_type ?? 'syslog';


        $query = "INSERT INTO siem_logs.security_logs 
                  (agent_id, hostname, log_type, message, severity, event_time, source_ip) 
                  VALUES ({$agent->id}, '{$agent->hostname}', '{$logType}', '{$message}', '{$severity}', now(), '{$agent->ip_address}')";

        try {
            $this->executeClickHouse($query);
            
            DB::table('agents')->where('id', $agent->id)->update(['last_heartbeat' => now()]);
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error("ClickHouse Store Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to store log'], 500);
        }
    }

    public function heartbeat(Request $request) 
    {
        $apiKey = $request->header('X-API-KEY');
        $agent = DB::table('agents')->where('api_key', $apiKey)->first();

        if (!$agent) return response()->json(['status' => 'unauthorized'], 401);

        DB::table('agents')->where('id', $agent->id)->update(['last_heartbeat' => now()]);
        return response()->json(['status' => 'alive']);
    }

    public function getLatestLogs(Request $request)
    {
        $hostname = $request->query('hostname');
        $logType = $request->query('log_type');
        $search = $request->query('search');

        $query = "SELECT formatDateTime(event_time, '%Y-%m-%d %H:%i:%s') as event_time, 
                  hostname, log_type, message 
                  FROM siem_logs.security_logs WHERE 1=1";

        if ($hostname) {
            $query .= " AND hostname = '{$hostname}'";
        }
        if ($logType) {
            $query .= " AND log_type LIKE '%{$logType}%'";
        }
        if ($search) {
             $query .= " AND message ILIKE '%{$search}%'"; 
        }

        $query .= " ORDER BY event_time DESC LIMIT 50 FORMAT JSON";
        
        try {
            $response = Http::withHeaders($this->chAuth)->withBody($query, 'text/plain')->post($this->chUrl);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['data' => []]);
        }
    }

    private function executeClickHouse($query)
    {
        $response = Http::withHeaders($this->chAuth)
            ->timeout(5)
            ->withBody($query, 'text/plain')
            ->post($this->chUrl);

        if ($response->failed()) {
            throw new \Exception("ClickHouse Execute Failed: " . $response->body());
        }
    }

    public function getBlacklist(Request $request)
    {
        $apiKey = $request->header('X-API-KEY');
        $agent  = DB::table('agents')->where('api_key', $apiKey)->first();

        if (!$agent) return response()->json(['status' => 'unauthorized'], 401);

        $ips = DB::table('ip_policies')
                ->where('action', 'block')
                ->pluck('ip_address'); // Return array: ['192.168.1.5', '10.0.0.2']

        return response()->json($ips);
    }

    public function getDashboardBlacklist()
    {
        $list = DB::table('ip_policies')
                  ->where('action', 'block')
                  ->orderBy('updated_at', 'desc')
                  ->get();

        return response()->json($list);
    }

    public function manualBlock(Request $request)
    {
        $request->validate(['ip_address' => 'required|ip']);

        DB::table('ip_policies')->updateOrInsert(
            ['ip_address' => $request->ip_address],
            [
                'action' => 'block',
                'reason' => 'Manual Block by Admin',
                'is_permanent' => true, 
                'updated_at' => now(),
                'created_at' => now()
            ]
        );

        return response()->json(['status' => 'success', 'message' => "IP {$request->ip_address} Blocked!"]);
    }

    public function manualUnblock(Request $request)
    {
        $request->validate(['ip_address' => 'required|ip']);

        DB::table('ip_policies')
            ->where('ip_address', $request->ip_address)
            ->delete();

        return response()->json(['status' => 'success', 'message' => "IP {$request->ip_address} Unblocked!"]);
    }

    public function waWebhook(Request $request) {
        $sender = $request->sender; 
        $message = strtolower($request->message);

        $isAdmin = DB::table('agents')
                    ->where('admin_phone', 'LIKE', '%"'.$sender.'"%')
                    ->exists();

        if (!$isAdmin) {
            DB::table('wa_histories')->insert([
                'sender' => $sender,
                'message' => $message,
                'status' => 'denied',
                'created_at' => now()
            ]);
            return response()->json(['reply' => "Maaf Bang, nomor Abang gak terdaftar sebagai Admin SIEM."]);
        }

        $reply = "Perintah tidak dikenal, Bang. Coba ketik 'status' atau 'cek ip [ip_address]'.";
        $commandType = null;

        if ($message == 'status') {
            $count = DB::table('agents')->count();
            $reply = "SIEM Online, Bang! Total Agent aktif: " . $count;
            $commandType = 'check_status';
        } 
        elseif (str_contains($message, 'blokir')) {
            $ip = trim(str_replace('blokir', '', $message));
            DB::table('ip_policies')->updateOrInsert(['ip_address' => $ip], ['action' => 'block', 'reason' => 'Manual WA Block', 'updated_at' => now()]);
            $reply = "Siap Bang! IP {$ip} sudah ana blokir ke seluruh agent.";
            $commandType = 'manual_block';
        }

        DB::table('wa_histories')->insert([
            'sender' => $sender,
            'message' => $message,
            'command' => $commandType,
            'status' => 'executed',
            'created_at' => now()
        ]);

        return response()->json(['reply' => $reply]);
    }
}