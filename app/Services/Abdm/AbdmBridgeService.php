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
     * Step 1: Update the bridge URL where HIP / HIU services are hosted.
     * Official V3 Endpoint: PATCH https://dev.abdm.gov.in/api/hiecm/gateway/v3/bridge/url
     */
    public function updateBridgeUrl(string $callbackUrl): array
    {
        $token = $this->client->getSessionToken();
        $targetUrl = rtrim($callbackUrl, '/');

        // Primary: Official ABDM Gateway V3 endpoint
        $v3Url = "{$this->client->getGatewayBaseUrl()}/gateway/v3/bridge/url";
        Log::info("ABDM Bridge: Updating bridge URL to {$targetUrl} at {$v3Url}");

        $response = Http::timeout(25)
            ->withHeaders($this->client->getStandardHeaders($token))
            ->patch($v3Url, [
                'url' => $targetUrl,
            ]);

        if ($response->successful()) {
            return [
                'status' => 'success',
                'message' => 'Bridge URL updated successfully in ABDM Gateway V3.',
                'data' => $response->json() ?? $response->body(),
            ];
        }

        $errorMsg = $response->json('message') 
            ?? $response->json('error.message') 
            ?? $response->body();

        Log::warning("ABDM Bridge V3 Update returned ({$response->status()}): {$errorMsg}");

        // Only try legacy /gateway/v1/bridges fallback if V3 route was not found (404)
        if ($response->status() === 404) {
            $v1Url = "{$this->client->getBridgeBaseUrl()}/gateway/v1/bridges";
            Log::info("ABDM Bridge: Trying legacy V1 fallback at {$v1Url}");

            $v1Resp = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => '*/*',
                    'X-CM-ID' => $this->client->getCmId(),
                ])
                ->patch($v1Url, [
                    'url' => $targetUrl,
                ]);

            if ($v1Resp->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'Bridge URL updated successfully (via V1 endpoint).',
                    'data' => $v1Resp->json() ?? $v1Resp->body(),
                ];
            }
        }

        throw new Exception("Failed to update Bridge URL ({$response->status()}): {$errorMsg}");
    }

    /**
     * Step 2: Add or update the services (HIP/HIU) to the bridge and mock facility registry.
     * Official V3 Endpoint: POST https://facilitysbx.abdm.gov.in/v1/bridges/MutipleHRPAddUpdateServices
     */
    public function addUpdateServices(?array $services = null): array
    {
        $token = $this->client->getSessionToken();
        $facilityId = $this->client->getHipId() ?: $this->client->getClientId();
        $facilityName = config('abdm.facility_name', 'Netrika Netralaya');
        $bridgeId = $this->client->getClientId();

        $facilityUrl = "https://facilitysbx.abdm.gov.in/v1/bridges/MutipleHRPAddUpdateServices";

        $payload = $services ?: [
            'facilityId' => $facilityId,
            'facilityName' => $facilityName,
            'HRP' => [
                [
                    'bridgeId' => $bridgeId,
                    'hipName' => $facilityName,
                    'type' => 'HIP',
                    'active' => true,
                ],
            ],
        ];

        Log::info("ABDM Bridge: Registering HIP service at {$facilityUrl}", ['payload' => $payload]);

        $response = Http::timeout(25)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post($facilityUrl, $payload);

        if ($response->successful()) {
            return [
                'status' => 'success',
                'message' => 'HIP service registered successfully in ABDM Facility Registry.',
                'data' => $response->json() ?? $response->body(),
            ];
        }

        // Fallback to legacy endpoint if facilitysbx returns 404
        if ($response->status() === 404) {
            $legacyUrl = "{$this->client->getBridgeBaseUrl()}/gateway/v1/bridges/addUpdateServices";
            $legResp = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => '*/*',
                    'X-CM-ID' => $this->client->getCmId(),
                ])
                ->post($legacyUrl, $payload);

            if ($legResp->successful()) {
                return [
                    'status' => 'success',
                    'message' => 'HIP service registered successfully to ABDM Bridge.',
                    'data' => $legResp->json() ?? $legResp->body(),
                ];
            }
        }

        $error = $response->body();
        Log::error("ABDM Bridge Service registration failed: {$error}");
        throw new Exception("Failed to add/update Bridge Services ({$response->status()}): {$error}");
    }

    /**
     * Step 3: View added services from the bridge to verify registration.
     * Official V3 Endpoint: GET https://dev.abdm.gov.in/api/hiecm/gateway/v3/bridge-services
     */
    public function getServices(): array
    {
        $token = $this->client->getSessionToken();
        $v3Url = "{$this->client->getGatewayBaseUrl()}/gateway/v3/bridge-services";

        Log::info("ABDM Bridge: Fetching registered services from {$v3Url}");

        $response = Http::timeout(20)
            ->withHeaders($this->client->getStandardHeaders($token))
            ->get($v3Url);

        if ($response->successful()) {
            $data = $response->json();
            return is_array($data) ? $data : ['response' => $data];
        }

        // Fallback to legacy if 404
        if ($response->status() === 404) {
            $v1Url = "{$this->client->getBridgeBaseUrl()}/gateway/v1/bridges/getServices";
            $v1Resp = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'X-CM-ID' => $this->client->getCmId(),
                ])
                ->get($v1Url);

            if ($v1Resp->successful()) {
                $data = $v1Resp->json();
                return is_array($data) ? $data : ['response' => $data];
            }
        }

        $error = $response->body();
        Log::error("ABDM Get Services failed: {$error}");
        throw new Exception("Failed to fetch Bridge Services ({$response->status()}): {$error}");
    }
}
