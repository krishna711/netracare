<?php

namespace App\Services\Abdm;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AbdmBridgeService
{
    protected AbdmClient $client;

    public function __construct(AbdmClient $client)
    {
        $this->client = $client;
    }

    /**
     * Step 1 from NHA email:
     * Update the bridge URL where HIP / HIU services are hosted.
     * Endpoint: PATCH https://dev.abdm.gov.in/gateway/v1/bridges
     */
    public function updateBridgeUrl(string $callbackUrl): array
    {
        $token = $this->client->getSessionToken();
        $url = "{$this->client->getBridgeBaseUrl()}/gateway/v1/bridges";

        Log::info("ABDM Bridge: Updating bridge URL to {$callbackUrl} at {$url}");

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => '*/*',
                'X-CM-ID' => $this->client->getCmId(),
            ])
            ->patch($url, [
                'url' => rtrim($callbackUrl, '/'),
            ]);

        if (!$response->successful()) {
            // Also try V3 endpoint format if v1 returns non-200
            $v3Url = "{$this->client->getGatewayBaseUrl()}/gateway/v3/bridge/url";
            $v3Resp = Http::timeout(20)
                ->withHeaders($this->client->getStandardHeaders($token))
                ->patch($v3Url, [
                    'url' => rtrim($callbackUrl, '/'),
                ]);

            if ($v3Resp->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'Bridge URL updated successfully (via V3 endpoint).',
                    'data' => $v3Resp->json() ?? $v3Resp->body(),
                ];
            }

            $error = $response->body() ?: $v3Resp->body();
            Log::error("ABDM Bridge URL Update failed: " . $error);
            throw new Exception("Failed to update Bridge URL ({$response->status()}): {$error}");
        }

        return [
            'status' => 'success',
            'message' => 'Bridge URL updated successfully in ABDM Gateway.',
            'data' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * Step 2 from NHA email:
     * Add or update the services (HIP/HIU) to the bridge and mock facility registry.
     * Endpoint: POST https://dev.abdm.gov.in/gateway/v1/bridges/addUpdateServices
     */
    public function addUpdateServices(?array $services = null): array
    {
        $token = $this->client->getSessionToken();
        $url = "{$this->client->getBridgeBaseUrl()}/gateway/v1/bridges/addUpdateServices";

        $hipId = $this->client->getHipId() ?: $this->client->getClientId();
        $facilityName = config('abdm.facility_name', 'Netrika Netralaya');
        $callbackUrl = config('abdm.public_callback_url', url('/'));

        // Default standard HIP registration payload if custom list not provided
        $payload = $services ?: [
            [
                'id' => $hipId,
                'name' => $facilityName,
                'type' => 'HIP',
                'active' => true,
                'alias' => [$facilityName, 'NetraCare-HIP'],
                'endpoints' => [
                    [
                        'address' => rtrim($callbackUrl, '/') . '/api/v3/hip/patient/share',
                        'connectionType' => 'https',
                        'use' => 'registration',
                    ],
                ],
            ],
        ];

        Log::info("ABDM Bridge: Registering HIP service for ID: {$hipId}");

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => '*/*',
                'X-CM-ID' => $this->client->getCmId(),
            ])
            ->post($url, $payload);

        if (!$response->successful()) {
            $error = $response->body();
            Log::error("ABDM Bridge Service registration failed: {$error}");
            throw new Exception("Failed to add/update Bridge Services ({$response->status()}): {$error}");
        }

        return [
            'status' => 'success',
            'message' => 'HIP service registered successfully to ABDM Bridge.',
            'data' => $response->json() ?? $response->body(),
        ];
    }

    /**
     * Step 3 from NHA email:
     * View added services from the bridge to verify registration.
     * Endpoint: GET https://dev.abdm.gov.in/gateway/v1/bridges/getServices
     */
    public function getServices(): array
    {
        $token = $this->client->getSessionToken();
        $url = "{$this->client->getBridgeBaseUrl()}/gateway/v1/bridges/getServices";

        Log::info("ABDM Bridge: Fetching registered services from {$url}");

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'X-CM-ID' => $this->client->getCmId(),
            ])
            ->get($url);

        if (!$response->successful()) {
            $error = $response->body();
            Log::error("ABDM Get Services failed: {$error}");
            throw new Exception("Failed to fetch Bridge Services ({$response->status()}): {$error}");
        }

        $data = $response->json();
        return is_array($data) ? $data : ['response' => $data];
    }
}
