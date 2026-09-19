<?php

namespace App\Console\Commands;

use App\Services\Abdm\AbdmBridgeService;
use App\Services\Abdm\AbdmClient;
use Illuminate\Console\Command;

class AbdmSyncBridgeCommand extends Command
{
    protected $signature = 'abdm:sync-bridge 
                            {--url= : Public callback URL for NetraCare (defaults to configured public_callback_url)}
                            {--hip= : HIP ID to test/register (defaults to IN2310001444)}';

    protected $description = 'Register and sync NetraCare Bridge URL and HIP Services with ABDM Gateway & Facility Registry';

    public function handle(AbdmClient $client, AbdmBridgeService $bridgeService): int
    {
        $this->info("=========================================================");
        $this->info("   ABDM Bridge Sync & Diagnostics (Milestone 1, 2, 3)    ");
        $this->info("=========================================================\n");

        $clientId = $client->getClientId();
        $configuredHipId = $client->getHipId() ?: 'IN2310001444';
        $hipId = $this->option('hip') ?: $configuredHipId;
        $url = $this->option('url') ?: (config('abdm.public_callback_url') ?: 'https://netracare.netrikanetralaya.com');

        $this->table(
            ['Parameter', 'Configured Value'],
            [
                ['Bridge / Client ID', $clientId],
                ['HIP ID', $hipId],
                ['Facility Name', config('abdm.facility_name', 'Netrika Netralaya')],
                ['Target Bridge Callback URL', $url],
                ['Gateway Base URL', $client->getGatewayBaseUrl()],
                ['Environment', config('abdm.env', 'sandbox')],
            ]
        );

        // Step 1: Session Token
        $this->info("\n[Step 1/4] Acquiring ABDM Session Token from Gateway...");
        try {
            $token = $client->getSessionToken(true);
            $this->info("✔ Session Token acquired successfully: " . substr($token, 0, 20) . "...");
        } catch (\Throwable $e) {
            $this->error("✖ Authentication Failed: " . $e->getMessage());
            return 1;
        }

        // Step 2: Query Gateway for existing HIP registration
        $this->info("\n[Step 2/4] Querying ABDM Gateway for existing HIP Service [{$hipId}]...");
        try {
            $serviceCheck = $bridgeService->getServiceByServiceId($hipId);
            if (($serviceCheck['status'] ?? '') === 'success') {
                $this->info("✔ Gateway recognizes HIP [{$hipId}]:");
                $this->line(json_encode($serviceCheck['data'], JSON_PRETTY_PRINT));
            } else {
                $this->warn("⚠ Gateway returned: " . ($serviceCheck['message'] ?? 'Service not found (HTTP ' . ($serviceCheck['code'] ?? '') . ')'));
            }
        } catch (\Throwable $e) {
            $this->warn("⚠ Could not query HIP service info: " . $e->getMessage());
        }

        // Step 3: Update Bridge Callback URL
        $this->info("\n[Step 3/4] Updating Bridge Callback URL to [{$url}] in ABDM Gateway V3...");
        try {
            $bridgeResult = $bridgeService->updateBridgeUrl($url);
            $this->info("✔ Bridge URL Updated: " . ($bridgeResult['message'] ?? 'Success'));
            if (!empty($bridgeResult['data'])) {
                $this->line(json_encode($bridgeResult['data'], JSON_PRETTY_PRINT));
            }
        } catch (\Throwable $e) {
            $this->error("✖ Failed to update Bridge URL: " . $e->getMessage());
        }

        // Step 4: Register HIP & HIU Services in Facility Sandbox
        $this->info("\n[Step 4/4] Registering HIP [{$hipId}] with ABDM Facility Registry...");
        try {
            $regResult = $bridgeService->addUpdateServices();
            $this->info("✔ Registration Result: " . ($regResult['message'] ?? 'Success'));
            if (!empty($regResult['data'])) {
                $this->line(json_encode($regResult['data'], JSON_PRETTY_PRINT));
            }
        } catch (\Throwable $e) {
            $this->warn("⚠ Service registration notice: " . $e->getMessage());
        }

        // Final verification check
        $this->info("\n[Verification] Checking registered Bridge Services in Gateway...");
        try {
            $services = $bridgeService->getServices();
            $this->info("✔ Registered Services found on Bridge:");
            $this->line(json_encode($services, JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            $this->warn("⚠ Could not list bridge services: " . $e->getMessage());
        }

        $this->info("\n=========================================================");
        $this->info("   Sync complete! You can now test Scan & Share with:    ");
        $this->info("   HIP ID: {$hipId}                                      ");
        $this->info("=========================================================\n");

        return 0;
    }
}
