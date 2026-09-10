<?php

namespace App\Services\Abdm;

use App\Models\Setting;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AbdmClient
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $gatewayBaseUrl;
    protected string $bridgeBaseUrl;
    protected string $abhaBaseUrl;
    protected string $cmId;
    protected string $hipId;

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Load settings with fallback to database settings table then config/env.
     */
    public function loadConfig(): void
    {
        $dbSettings = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $dbSettings = Setting::where('key', 'like', 'abdm_%')->pluck('value', 'key')->toArray();
            }
        } catch (\Throwable $e) {
            // Fallback to config
        }

        $this->clientId = trim($dbSettings['abdm_client_id'] ?? config('abdm.client_id', ''));
        $this->clientSecret = trim($dbSettings['abdm_client_secret'] ?? config('abdm.client_secret', ''));
        $this->gatewayBaseUrl = rtrim($dbSettings['abdm_gateway_url'] ?? config('abdm.gateway_base_url', 'https://dev.abdm.gov.in/api/hiecm'), '/');
        $this->bridgeBaseUrl = rtrim($dbSettings['abdm_bridge_url'] ?? config('abdm.bridge_base_url', 'https://dev.abdm.gov.in'), '/');
        $this->abhaBaseUrl = rtrim($dbSettings['abdm_abha_url'] ?? config('abdm.abha_base_url', 'https://abhasbx.abdm.gov.in/abha/api'), '/');
        $this->cmId = trim($dbSettings['abdm_cm_id'] ?? config('abdm.cm_id', 'sbx'));
        $this->hipId = trim($dbSettings['abdm_hip_id'] ?? config('abdm.hip_id', ''));
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getHipId(): string
    {
        return $this->hipId;
    }

    public function getGatewayBaseUrl(): string
    {
        return $this->gatewayBaseUrl;
    }

    public function getBridgeBaseUrl(): string
    {
        return $this->bridgeBaseUrl;
    }

    public function getAbhaBaseUrl(): string
    {
        return $this->abhaBaseUrl;
    }

    public function getCmId(): string
    {
        return $this->cmId;
    }

    /**
     * Generate or retrieve cached ABDM Gateway Session Access Token.
     *
     * @param bool $forceRefresh
     * @return string
     * @throws Exception
     */
    public function getSessionToken(bool $forceRefresh = false): string
    {
        $cacheKey = 'abdm_gateway_session_token_' . md5($this->clientId);

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception("ABDM Client ID and Client Secret must be configured in Settings or .env.");
        }

        $url = "{$this->gatewayBaseUrl}/gateway/v3/sessions";
        $requestId = (string) Str::uuid();
        $timestamp = now()->toIso8601String();

        Log::info("ABDM: Requesting session token from {$url} [RequestID: {$requestId}]");

        $response = Http::timeout(15)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'REQUEST-ID' => $requestId,
                'TIMESTAMP' => $timestamp,
                'X-CM-ID' => $this->cmId,
            ])
            ->post($url, [
                'clientId' => $this->clientId,
                'clientSecret' => $this->clientSecret,
                'grantType' => 'client_credentials',
            ]);

        if (!$response->successful()) {
            $errorMsg = $response->json('message') 
                ?? $response->json('error.message') 
                ?? $response->json('error') 
                ?? $response->body();

            Log::error("ABDM Session Error [{$response->status()}]: {$errorMsg}");
            throw new Exception("ABDM Authentication Failed ({$response->status()}): {$errorMsg}");
        }

        $data = $response->json();
        $token = $data['accessToken'] ?? $data['token'] ?? null;
        $expiresIn = (int) ($data['expiresIn'] ?? 1200);

        if (empty($token)) {
            throw new Exception("Invalid response from ABDM Gateway: accessToken missing.");
        }

        // Cache token with safe margin (refresh 60 seconds before actual expiration)
        $ttl = max(60, $expiresIn - 60);
        Cache::put($cacheKey, $token, $ttl);

        Log::info("ABDM: Session token generated successfully. Valid for {$expiresIn}s.");
        return $token;
    }

    /**
     * Fetch ABDM Public Encryption Certificate from Gateway/ABHA API.
     *
     * @param bool $forceRefresh
     * @return string
     * @throws Exception
     */
    public function getPublicCertificate(bool $forceRefresh = false): string
    {
        $cacheKey = 'abdm_public_cert_' . $this->cmId;

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Primary endpoint in ABDM v3
        $url = "{$this->abhaBaseUrl}/v3/profile/public/certificate";
        $token = $this->getSessionToken();

        Log::info("ABDM: Fetching public certificate from {$url}");

        $response = Http::timeout(15)
            ->withHeaders($this->getStandardHeaders($token))
            ->get($url);

        if (!$response->successful()) {
            // Fallback to gateway certs endpoint
            $fallbackUrl = "{$this->gatewayBaseUrl}/gateway/v3/certs";
            Log::warning("ABDM Cert fallback: Trying {$fallbackUrl}");

            $fallbackResp = Http::timeout(15)
                ->withHeaders($this->getStandardHeaders($token))
                ->get($fallbackUrl);

            if (!$fallbackResp->successful()) {
                throw new Exception("Failed to retrieve ABDM Public Certificate: " . ($response->body() ?: $fallbackResp->body()));
            }

            $response = $fallbackResp;
        }

        $cert = null;
        $body = $response->body();

        // Check if response is raw string or JSON
        if (str_starts_with(trim($body), '{')) {
            $json = $response->json();
            $cert = $json['publicKey'] ?? $json['certificate'] ?? $json['cert'] ?? null;
        } else {
            $cert = $body;
        }

        if (empty($cert)) {
            throw new Exception("Public key certificate not found in ABDM response.");
        }

        // Cache for 24 hours
        Cache::put($cacheKey, $cert, now()->addHours(24));
        return $cert;
    }

    /**
     * Get default standard headers required for ABDM V3 requests.
     */
    public function getStandardHeaders(?string $bearerToken = null, ?string $xToken = null): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP' => now()->toIso8601String(),
            'X-CM-ID' => $this->cmId,
        ];

        if ($bearerToken) {
            $headers['Authorization'] = 'Bearer ' . $bearerToken;
        }

        if ($xToken) {
            $headers['X-Token'] = 'Bearer ' . $xToken;
        }

        return $headers;
    }

    /**
     * Execute an authenticated request against ABDM.
     *
     * @param string $method GET, POST, PUT, PATCH, DELETE
     * @param string $url Full URL or endpoint path
     * @param array $payload JSON payload
     * @param string|null $xToken Patient profile / user X-Token
     * @return Response
     * @throws Exception
     */
    public function sendRequest(string $method, string $url, array $payload = [], ?string $xToken = null): Response
    {
        $accessToken = $this->getSessionToken();
        $headers = $this->getStandardHeaders($accessToken, $xToken);

        $http = Http::timeout(30)->withHeaders($headers);

        $response = match (strtoupper($method)) {
            'GET' => $http->get($url, $payload),
            'POST' => $http->post($url, $payload),
            'PATCH' => $http->patch($url, $payload),
            'PUT' => $http->put($url, $payload),
            'DELETE' => $http->delete($url, $payload),
            default => throw new Exception("Unsupported HTTP method: {$method}"),
        };

        if ($response->status() === 401) {
            // Token may have expired prematurely; clear cache and retry once
            Log::warning("ABDM 401 Unauthorized: Refreshing token and retrying...");
            $freshToken = $this->getSessionToken(true);
            $headers = $this->getStandardHeaders($freshToken, $xToken);
            $http = Http::timeout(30)->withHeaders($headers);

            $response = match (strtoupper($method)) {
                'GET' => $http->get($url, $payload),
                'POST' => $http->post($url, $payload),
                'PATCH' => $http->patch($url, $payload),
                'PUT' => $http->put($url, $payload),
                'DELETE' => $http->delete($url, $payload),
            };
        }

        return $response;
    }

    /**
     * Perform live test connection to check credentials & certificate retrieval.
     */
    public function testConnection(): array
    {
        try {
            $startTime = microtime(true);
            $token = $this->getSessionToken(true);
            $sessionLatency = round((microtime(true) - $startTime) * 1000, 2);

            $certStart = microtime(true);
            $cert = $this->getPublicCertificate(true);
            $certLatency = round((microtime(true) - $certStart) * 1000, 2);

            return [
                'status' => 'success',
                'message' => 'Connected successfully to ABDM Gateway & ABHA Sandbox!',
                'client_id' => $this->clientId,
                'cm_id' => $this->cmId,
                'gateway_url' => $this->gatewayBaseUrl,
                'token_sample' => substr($token, 0, 15) . '...' . substr($token, -10),
                'session_latency_ms' => $sessionLatency,
                'cert_retrieved' => !empty($cert),
                'cert_latency_ms' => $certLatency,
                'timestamp' => now()->toDateTimeString(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'client_id' => $this->clientId,
                'cm_id' => $this->cmId,
                'timestamp' => now()->toDateTimeString(),
            ];
        }
    }
}
