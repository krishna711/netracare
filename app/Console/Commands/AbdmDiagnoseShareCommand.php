<?php

namespace App\Console\Commands;

use App\Services\Abdm\AbdmBridgeService;
use App\Services\Abdm\AbdmClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AbdmDiagnoseShareCommand extends Command
{
    protected $signature = 'abdm:diagnose-share';
    protected $description = 'Comprehensive diagnosis of ABDM Milestone 1 Scan & Share connectivity, bridge URLs, and Gateway state';

    public function handle(AbdmClient $client, AbdmBridgeService $bridgeService): int
    {
        $this->info("=========================================================");
        $this->info("   ABDM Scan & Share Full System Diagnostic              ");
        $this->info("=========================================================\n");

        $hipId = $client->getHipId() ?: 'IN2310001444';
        $bridgeId = $client->getClientId();
        $publicUrl = config('abdm.public_callback_url') ?: 'https://netracare.netrikanetralaya.com';

        $this->table(
            ['Configuration Item', 'Value'],
            [
                ['Bridge ID (Client ID)', $bridgeId],
                ['HIP ID', $hipId],
                ['Facility Name', config('abdm.facility_name', 'Netrika Netralaya')],
                ['Configured Public Callback URL', $publicUrl],
                ['Gateway Base URL', $client->getGatewayBaseUrl()],
                ['Environment', config('abdm.env', 'sandbox')],
            ]
        );

        // 1. Session Token
        $this->info("\n--- [1] Gateway V3 Authentication ---");
        try {
            $token = $client->getSessionToken(true);
            $this->info("✔ Session Token acquired successfully (length: " . strlen($token) . " chars)");
        } catch (\Throwable $e) {
            $this->error("✖ Gateway Authentication Failed: " . $e->getMessage());
            return 1;
        }

        // 2. Gateway V3 Registered Bridge Info
        $this->info("\n--- [2] Gateway V3 Bridge Callback URL & Services ---");
        try {
            $servicesV3 = $bridgeService->getServices();
            $bridgeData = $servicesV3['data']['bridge'] ?? $servicesV3['bridge'] ?? [];
            $v3Url = $bridgeData['url'] ?? 'NOT FOUND';
            $isActive = ($bridgeData['active'] ?? false) ? 'YES' : 'NO';
            $isBlocked = ($bridgeData['blocklisted'] ?? false) ? 'YES' : 'NO';

            $this->info("Bridge ID in Gateway: " . ($bridgeData['id'] ?? $bridgeId));
            $this->info("Registered Bridge URL: {$v3Url}");
            $this->info("Active: {$isActive} | Blocklisted: {$isBlocked}");

            if ($v3Url !== $publicUrl) {
                $this->warn("⚠ MISMATCH: Registered URL ({$v3Url}) does not match Configured URL ({$publicUrl})!");
            } else {
                $this->info("✔ Bridge URL matches configured callback URL.");
            }

            $services = $servicesV3['data']['services'] ?? $servicesV3['services'] ?? [];
            $this->info("Registered Services (" . count($services) . "):");
            foreach ($services as $svc) {
                $this->line("  - [{$svc['id']}] {$svc['name']} (Types: " . implode(',', $svc['types'] ?? []) . ", Active: " . (($svc['active'] ?? false) ? 'true' : 'false') . ")");
            }
        } catch (\Throwable $e) {
            $this->warn("⚠ Could not query Gateway V3 Bridge Services: " . $e->getMessage());
        }

        // 3. Check HIP Service Details
        $this->info("\n--- [3] Gateway V3 Service Lookup for HIP [{$hipId}] ---");
        try {
            $hipCheck = $bridgeService->getServiceByServiceId($hipId);
            if (($hipCheck['status'] ?? '') === 'success') {
                $this->info("✔ Gateway recognizes HIP [{$hipId}]:");
                $this->line(json_encode($hipCheck['data'], JSON_PRETTY_PRINT));
            } else {
                $this->warn("⚠ Gateway returned: " . ($hipCheck['message'] ?? 'Service not found'));
            }
        } catch (\Throwable $e) {
            $this->warn("⚠ Could not query service ID: " . $e->getMessage());
        }

        // 4. Test Local Webhook Endpoint Loopback
        $this->info("\n--- [4] Local Public Webhook Accessibility Test ---");
        $endpointsToTest = [
            '/api/v3/hip/patient/share',
            '/v3/hip/patient/share',
            '/api/v3/hip/patient/running-token/status',
            '/patients/profile/share',
        ];

        foreach ($endpointsToTest as $ep) {
            $testUrl = rtrim($publicUrl, '/') . $ep;
            try {
                $start = microtime(true);
                $resp = Http::timeout(8)->withHeaders([
                    'Content-Type' => 'application/json',
                    'REQUEST-ID' => (string) Str::uuid(),
                    'X-CM-ID' => 'sbx',
                    'User-Agent' => 'ABDM-Gateway-Simulator/1.0',
                ])->post($testUrl, [
                    'intent' => 'DIAGNOSTIC_PING',
                    'metaData' => [
                        'hipId' => $hipId,
                        'context' => '1',
                    ],
                ]);
                $elapsed = round((microtime(true) - $start) * 1000);
                $status = $resp->status();
                $serverHeader = $resp->header('Server') ?: 'unknown';

                if ($status === 200) {
                    $this->info("✔ [{$status} OK] in {$elapsed}ms: {$testUrl} (Server: {$serverHeader})");
                } else {
                    $this->warn("⚠ [{$status}] in {$elapsed}ms: {$testUrl} (Server: {$serverHeader})");
                }
            } catch (\Throwable $e) {
                $this->error("✖ FAILED: {$testUrl} - " . $e->getMessage());
            }
        }

        // 5. Inspect Raw Incoming Traffic Log
        $this->info("\n--- [5] Checking storage/logs/abdm_gateway.log ---");
        $logFile = storage_path('logs/abdm_gateway.log');
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $count = count($lines);
            $this->info("Found {$count} entries in abdm_gateway.log. Last 5 entries:");
            foreach (array_slice($lines, -5) as $l) {
                $this->line("  " . trim($l));
            }
        } else {
            $this->warn("⚠ storage/logs/abdm_gateway.log does not exist yet. No ABDM traffic has reached public/index.php.");
        }

        $this->info("\n=========================================================");
        $this->info("   Diagnosis Complete!                                   ");
        $this->info("=========================================================\n");

        return 0;
    }
}
