<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\AbuseIPDB;

class SiemAnalyzer extends Command
{
    protected $signature = 'siem:analyze';
    protected $description = 'SIEM Intelligence: Detect, AI Analyze, and Notify with Auth';

   public function handle()
    {
        $this->info("NAPM-Assistant Sentinel is active and monitoring...");

        while (true) {
            try {
                $rules = DB::table('detection_rules')->where('is_active', true)->get();
                 if ($rules->isEmpty()) {
                    $this->warn('No active detection rules found. Checking again in 10s...');
                    DB::disconnect(); 
                    sleep(10);
                    continue;
                }

                foreach ($rules as $rule) {
                    $this->analyzeRule($rule);
                }
                $this->info("Cycle completed at " . now()->toDateTimeString() . ". Waiting for next batch...");
            } catch (\Exception $e) {
                $this->error("Error in loop: " . $e->getMessage());
            } finally {
                DB::disconnect();
                sleep(5); 
                gc_collect_cycles();
            }
        }
    }

    private function analyzeRule($rule)
    {
        $regexMap = [
            'nginx'   => '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})', 
            'apache'  => '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})',
            'xampp'   => '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})',
            'lampp'   => '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})',
            'apache2' => '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})',
            'ssh'     => 'from (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})',
            'auth'    => 'from (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})',
            'mysql'   => '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})', 
        ];

        $regexPattern = $regexMap[$rule->log_type] ?? '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})';

       $sql = "SELECT " .
               "extract(message, '{$regexPattern}') AS attacker_ip, " .
               "hostname, " .
               "count() AS total_hits, " .
               "groupArray(5)(message) AS log_samples " . 
               "FROM siem_logs.security_logs " .
               "WHERE log_type = '{$rule->log_type}' " .
               "AND message ILIKE '%{$rule->search_keyword}%' " .
               "AND event_time >= now() - INTERVAL {$rule->time_window_m} MINUTE " .
               "GROUP BY attacker_ip, hostname " .
               "HAVING total_hits >= {$rule->threshold}";
        try {
            $host = env('CLICKHOUSE_HOST', '127.0.0.1');
            $user = env('CLICKHOUSE_USER', 'default');
            $pass = env('CLICKHOUSE_PASSWORD'); 
            $port = env('CLICKHOUSE_PORT', '8123');

            $response = Http::withHeaders([
                'X-ClickHouse-User' => $user,
                'X-ClickHouse-Key'  => $pass,
            ])->withBody($sql . " FORMAT JSON", 'text/plain')
              ->post("http://{$host}:{$port}/");

            if ($response->failed()) {
                $this->error("ClickHouse Error [{$rule->name}]: " . $response->body());
                return;
            }

            $results = $response->json()['data'] ?? [];
            
            if (empty($results)) {
                // Verbose option: $this->line("Rule '{$rule->name}': Clean.");
                return;
            }

            foreach ($results as $hacker) {
                // Validasi IP dasar
                if (!empty($hacker['attacker_ip']) && $hacker['attacker_ip'] !== '0.0.0.0') {
                    $this->processAlert($rule, $hacker);
                }
            }

        } catch (\Exception $e) {
            $this->error("🔥 Critical Error processing rule {$rule->name}: " . $e->getMessage());
        }
    }

   private function processAlert($rule, $data)
    {
        $ip = $data['attacker_ip'];
        $hostname = $data['hostname'];

        // 1. Whitelist Check (Penting agar tidak memblokir IP internal)
        if (str_starts_with($ip, '127.') || str_starts_with($ip, '10.88') || str_starts_with($ip, '192.168.')) {
            return;
        }

        // 2. Anti-Spam / Rate Limiting Alert
        $exists = DB::table('security_alerts')
                    ->where('attacker_ip', $ip)
                    ->where('rule_id', $rule->id) 
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->exists();
        if ($exists) return;

         $existsing = DB::table('ip_policies')
                    ->where('ip_address', $ip)
                    ->exists();
        if ($existsing) return;

        $this->info("PROCESSING: $ip on $hostname (Rule: {$rule->name})");

        // 3. Reputation Check
        $reputation = AbuseIPDB::check($ip);
        $abuseScore = $reputation->abuse_score ?? 0;
        $country = $reputation->country ?? 'Unknown';
        $isp = $reputation->isp ?? 'Unknown';

        $logSamplesStr = is_array($data['log_samples']) ? implode("\n", $data['log_samples']) : $data['log_samples'];
        $aiAnalysis = "";
        $aiSuggestsBan = false;

        if ($abuseScore >= 85) {
            $this->warn("High Risk IP ($abuseScore%). Fast-tracking block.");
            $aiAnalysis = "**HIGH RISK DETECTED BY REPUTATION DATABASE**\n\n";
            $aiAnalysis .= "• Score: {$abuseScore}%\n• ISP: {$isp}\n";
            $aiAnalysis .= "• [FINAL VERDICT]: **YA**. IP ini adalah residivis berbahaya. #ACTION_BAN#";
        } else {
            $this->info("Asking NAPM-Assistant AI ($abuseScore%)...");
            $contextData = "IP Location: $country | ISP: $isp | AbuseIPDB Score: $abuseScore%";
            $aiAnalysis = $this->askAI($logSamplesStr, $rule->name, $contextData);
        }

        $aiSuggestsBan = str_contains($aiAnalysis, '#ACTION_BAN#');
        $shouldBlock = $rule->auto_block || ($abuseScore == 100) || $aiSuggestsBan;

        DB::table('security_alerts')->insert([
            'rule_id'     => $rule->id,
            'attacker_ip' => $ip,
            'hostname'    => $hostname,
            'hits'        => $data['total_hits'],
            'evidence'    => str_replace(['#ACTION_BAN#', '#ACTION_ALLOW#'], '', $aiAnalysis),
            'abuse_score' => $abuseScore,
            'created_at'  => now()
        ]);

        $emoji = $shouldBlock ? "🔴" : ($abuseScore >= 30 ? "🟠" : "🟢");
        $cleanAnalysis = str_replace(['#ACTION_BAN#', '#ACTION_ALLOW#'], '', $aiAnalysis);
        
        $pesan = "*NAPM SIEM INTELLIGENCE REPORT*\n\n";
        $pesan .= "Target: `{$hostname}`\n";
        $pesan .= "Attacker: `{$ip}` ({$country})\n";
        $pesan .= "Reputation: {$abuseScore}% {$emoji}\n";
        $pesan .= "Triggered Rule: *{$rule->name}*\n";
        $pesan .= "----------------------------------\n";
        $pesan .= $cleanAnalysis . "\n";
        $pesan .= "----------------------------------\n";

        if ($shouldBlock) {
            DB::table('ip_policies')->updateOrInsert(['ip_address' => $ip], [
                'action'     => 'block',
                'reason'     => "SIEM Auto-Ban: {$rule->name} (Score: $abuseScore% | AI Verified)",
                'updated_at' => now()
            ]);
            $pesan .= "\n*SYSTEM ACTION: IP BLOCKED AUTOMATICALLY*";
        } else {
            $pesan .= "\n*SYSTEM ACTION: FLAGGED FOR REVIEW*";
        }

        $this->dispatchNotifications($hostname, $pesan);
    }

    private function askAI($logs, $ruleName, $contextData)
    {
        $prompt = "Identity: Anda adalah NAPM-Assistant, Unit Cyber Security Intelligence elit.\n";
        $prompt .= "Context: Sistem mendeteksi anomali pada aturan: '{$ruleName}'.\n";
        $prompt .= "Intel Data: {$contextData}\n\n";
        $prompt .= "Evidence (Log Sample):\n{$logs}\n\n";
        $prompt .= "Directive: Lakukan analisis forensik singkat dengan format:\n";
        $prompt .= "1. [THREAT ANALYSIS]: Identifikasi jenis serangan.\n";
        $prompt .= "2. [STRATEGIC MITIGATION]: Langkah teknis konkret.\n";
        $prompt .= "3. [FINAL VERDICT]: Rekomendasi (YA/TIDAK) ban IP ini.\n\n";
        $prompt .= "PENTING: Jika menurut Anda log ini berbahaya, wajib akhiri dengan kode #ACTION_BAN#. ";
        $prompt .= "Jika ini kemungkinan false positive atau tidak berbahaya, akhiri dengan #ACTION_ALLOW#.\n";
        $prompt .= "Tone: Tegas, Tanpa Basa-basi.";

        // 1. Coba Groq Terlebih Dahulu
        $groqKey = DB::table('settings')->where('key', 'groq_key')->value('value');
        $groqModel = DB::table('settings')->where('key', 'ai_model')->value('value') ?? 'llama-3.3-70b-versatile';

        if ($groqKey) {
            try {
                $response = Http::timeout(20)->withToken($groqKey)->post("https://api.groq.com/openai/v1/chat/completions", [
                    'model'    => $groqModel,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.1 
                ]);

                if ($response->successful()) {
                    return $response->json()['choices'][0]['message']['content'];
                }
                
                $this->warn("⚠️ Groq API Error/Limit. Switching to Gemini...");
            } catch (\Exception $e) {
                $this->error("🔥 Groq Exception: " . $e->getMessage() . ". Switching to Gemini...");
            }
        }

        // 2. Jika Groq Gagal atau Key Tidak Ada, Alihkan ke Gemini
        return $this->askGemini($prompt);
    }

    private function askGemini($prompt)
    {
        $geminiKey = DB::table('settings')->where('key', 'gemini_key')->value('value');
        
        if (!$geminiKey) {
            return "Error: Groq Gagal & Gemini Key belum di-set.";
        }

        try {
            $response = Http::timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $geminiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                ]
            ]);

            if ($response->successful()) {
                return $response->json()['candidates'][0]['content']['parts'][0]['text'];
            }

            return "AI Failover Error: Gemini juga tidak tersedia.";
        } catch (\Exception $e) {
            return "AI Critical Error: " . $e->getMessage();
        }
    }
    
    private function dispatchNotifications($hostname, $pesan)
    {
        $agent = DB::table('agents')->where('hostname', $hostname)->first();
        if (!$agent) return;

        $channels = json_decode($agent->notification_channels ?? '["telegram"]', true);
        $waNumbers = json_decode($agent->admin_phone ?? '[]', true);

        if (in_array('telegram', $channels)) {
            try {
                Http::timeout(5)->post("https://api.telegram.org/bot".env('TELEGRAM_BOT_TOKEN')."/sendMessage", [
                    'chat_id'    => env('TELEGRAM_CHAT_ID'), 
                    'text'       => $pesan, 
                    'parse_mode' => 'Markdown'
                ]);
            } catch (\Exception $e) {
                Log::error("Telegram fail: " . $e->getMessage());
            }
        }

        if (in_array('whatsapp', $channels) && !empty($waNumbers)) {
            foreach ($waNumbers as $num) {
                try {
                    Http::timeout(2)->post("http://localhost:3111/send-broadcast", [
                        'to'      => $num, 
                        'message' => $pesan
                    ]);
                } catch (\Exception $e) {
                    Log::error("WA fail to $num: " . $e->getMessage());
                }
            }
        }
    }
}