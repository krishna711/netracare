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
     * Endpoint from NHA Instructions: POST https://dev.abdm.gov.in/gateway/v1/bridges/addUpdateServices
     */
    public function addUpdateServices(?array $services = null): array
    {
        $token = $this->client->getSessionToken();
        $hipId = $this->client->getHipId() ?: $this->client->getClientId();
        $facilityName = config('abdm.facility_name', 'Netrika Netralaya');
        $callbackUrl = config('abdm.public_callback_url', url('/'));

        // Standard NHA payload structure (list of bridge services)
        $payload = $services ?: [
            [
                'id' => $hipId,
                'name' => $facilityName,
                'type' => 'HIP',
                'active' => true,
                'alias' => array_values(array_unique([$facilityName, 'Netrika Netralaya', 'Netrika', 'Netralaya'])),
                'endpoints' => [
                    [
                        'address' => rtrim($callbackUrl, '/') . '/api/v3/hip/patient/share',
                        'connectionType' => 'https',
                        'use' => 'registration',
                    ],
                ],
            ],
        ];

        // Candidate Payload A: Q17 HRP Object Format
        $hrpPayload = [
            'facilityId' => $hipId,
            'facilityName' => $facilityName,
            'HRP' => [
                [
                    'bridgeId' => $this->client->getClientId(),
                    'hipName' => $facilityName,
                    'type' => 'HIP',
                    'active' => true,
                ],
            ],
        ];

        // Candidate Payload B: Standard Bridge Services Array (with HIP ID)
        $payloadWithHipId = $payload;

        // Candidate Payload C: Standard Bridge Services Array (with Bridge ID as SERVICE_ID)
        $payloadWithBridgeId = [
            [
                'id' => $this->client->getClientId(),
                'name' => $facilityName,
                'type' => 'HIP',
                'active' => true,
                'alias' => array_values(array_unique([$facilityName, 'Netrika Netralaya', 'Netrika'])),
                'endpoints' => [
                    [
                        'address' => rtrim($callbackUrl, '/') . '/api/v3/hip/patient/share',
                        'connectionType' => 'https',
                        'use' => 'registration',
                    ],
                ],
            ],
        ];

        $attempts = [
            [
                'label' => 'dev.abdm.gov.in /addUpdateServices (Q17 HRP format)',
                'url' => "{$this->client->getBridgeBaseUrl()}/gateway/v1/bridges/addUpdateServices",
                'data' => $hrpPayload,
                'method' => 'POST',
            ],
            [
                'label' => 'dev.abdm.gov.in /addUpdateServices (Service Array with HIP ID)',
                'url' => "{$this->client->getBridgeBaseUrl()}/gateway/v1/bridges/addUpdateServices",
                'data' => $payloadWithHipId,
                'method' => 'POST',
            ],
            [
                'label' => 'dev.abdm.gov.in /addUpdateServices (Service Array with Bridge ID)',
                'url' => "{$this->client->getBridgeBaseUrl()}/gateway/v1/bridges/addUpdateServices",
                'data' => $payloadWithBridgeId,
                'method' => 'POST',
            ],
            [
                'label' => 'dev.abdm.gov.in /MutipleHRPAddUpdateServices (Q17 HRP format)',
                'url' => "{$this->client->getBridgeBaseUrl()}/gateway/v1/bridges/MutipleHRPAddUpdateServices",
                'data' => $hrpPayload,
                'method' => 'POST',
            ],
            [
                'label' => 'V3 bridge-service (PUT)',
                'url' => "{$this->client->getGatewayBaseUrl()}/gateway/v3/bridge-service",
                'data' => $payloadWithHipId[0],
                'method' => 'PUT',
            ],
            [
                'label' => 'facilitysbx MutipleHRP (Q17)',
                'url' => 'https://facilitysbx.abdm.gov.in/v1/bridges/MutipleHRPAddUpdateServices',
                'data' => $hrpPayload,
                'method' => 'POST',
                'timeout' => 3,
            ],
        ];

        $errors = [];

        foreach ($attempts as $attempt) {
            $label = $attempt['label'];
            $targetUrl = $attempt['url'];
            $body = $attempt['data'];
            $method = $attempt['method'];
            $timeout = $attempt['timeout'] ?? 15;

            Log::info("ABDM Bridge: Attempting {$label} at {$targetUrl}", ['payload' => $body]);

            try {
                $req = Http::timeout($timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/json',
                        'Accept' => '*/*',
                        'X-CM-ID' => $this->client->getCmId(),
                    ]);

                $resp = $method === 'PUT' ? $req->put($targetUrl, $body) : $req->post($targetUrl, $body);

                if ($resp->successful()) {
                    Log::info("ABDM Bridge: SUCCESS on {$label}!", ['response' => $resp->json()]);
                    return [
                        'status' => 'success',
                        'message' => "HIP service registered successfully via {$label}.",
                        'data' => $resp->json() ?? $resp->body(),
                    ];
                }

                $respBody = $resp->body();
                $errors[] = "[{$label}] HTTP {$resp->status()}: " . substr($respBody, 0, 150);
                Log::warning("ABDM Bridge {$label} returned {$resp->status()}: {$respBody}");
            } catch (\Throwable $e) {
                $errors[] = "[{$label}] Exception: " . $e->getMessage();
                Log::warning("ABDM Bridge {$label} exception: " . $e->getMessage());
            }
        }

        $allErrors = implode("\n", $errors);
        Log::error("ABDM Bridge: All registration attempts failed:\n{$allErrors}");

        if (str_contains($allErrors, '900908') || str_contains($allErrors, 'API Subscription validation failed')) {
            throw new Exception(
                "NHA Sandbox Notice (403): Bridge Client ID is not subscribed to the legacy V1 addUpdateServices API.\n\n" .
                "Attempts summary:\n{$allErrors}\n\n" .
                "In ABDM V3, NHA requires backend mapping or Software Linkage on the portal. " .
                "Since 'Get Details' on HFR returns 'No Existing Data', Bridge SBXID_075083 must be mapped to IN2310014055 by NHA support: integration.support@nha.gov.in."
            );
        }

        throw new Exception("Failed to register Bridge Services. Results:\n{$allErrors}");
    }

    /**
     * Step 3: View added services from the bridge to verify registration.
     * Endpoint: GET https://dev.abdm.gov.in/api/hiecm/gateway/v3/bridge-services
     * Fallback: GET https://dev.abdm.gov.in/gateway/v1/bridges/getServices
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
            Log::info("ABDM Bridge: getServices response", ['data' => $data]);
            return is_array($data) ? $data : ['response' => $data];
        }

        // Fallback to legacy endpoint from NHA email
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
            Log::info("ABDM Bridge: getServices V1 response", ['data' => $data]);
            return is_array($data) ? $data : ['response' => $data];
        }

        $error = $response->body() ?: $v1Resp->body();
        Log::error("ABDM Get Services failed: {$error}");
        throw new Exception("Failed to fetch Bridge Services ({$response->status()}): {$error}");
    }
}
