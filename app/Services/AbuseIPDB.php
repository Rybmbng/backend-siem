<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbuseIPDB
{
    public static function check($ip)
    {
        $cached = DB::table('ip_reputations')
                    ->where('ip_address', $ip)
                    ->where('last_checked_at', '>=', now()->subHours(24))
                    ->first();

        if ($cached) {
            return $cached;
        }

        try {
            $apiKey = DB::table('settings')->where('key', 'abuseipdb_key')->value('value');
            
            if (!$apiKey) return null;

            $response = Http::withHeaders([
                'Key' => $apiKey,
                'Accept' => 'application/json'
            ])->get("https://api.abuseipdb.com/api/v2/check", [
                'ipAddress' => $ip,
                'maxAgeInDays' => 90
            ]);

            if ($response->successful()) {
                $data = $response->json()['data'];

                DB::table('ip_reputations')->updateOrInsert(
                    ['ip_address' => $ip],
                    [
                        'abuse_score' => $data['abuseConfidenceScore'],
                        'country' => $data['countryCode'],
                        'isp' => $data['isp'],
                        'usage_type' => $data['usageType'],
                        'last_checked_at' => now(),
                        'updated_at' => now()
                    ]
                );

                return (object) [
                    'abuse_score' => $data['abuseConfidenceScore'],
                    'country' => $data['countryCode'],
                    'isp' => $data['isp'],
                    'usage_type' => $data['usageType']
                ];
            }
        } catch (\Exception $e) {
            Log::error("AbuseIPDB Error: " . $e->getMessage());
        }

        return null;
    }
}