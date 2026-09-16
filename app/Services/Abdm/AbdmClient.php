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

    public function getIsoTimestamp(): string
    {
        return gmdate('Y-m-d\TH:i:s.v\Z');
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
        $timestamp = $this->getIsoTimestamp();

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
     * Get v0.5 session token for legacy / devservice ABDM endpoints.
     *
     * @param bool $forceRefresh
     * @return string
     * @throws Exception
     */
    public function getV05SessionToken(bool $forceRefresh = false): string
    {
        $cacheKey = 'abdm_v05_session_token_' . md5($this->clientId);

        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception("ABDM Client ID and Client Secret must be configured in Settings or .env.");
        }

        $url = "https://dev.abdm.gov.in/gateway/v0.5/sessions";
        Log::info("ABDM: Requesting v0.5 session token from {$url}");

        $response = Http::timeout(15)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($url, [
                'clientId' => $this->clientId,
                'clientSecret' => $this->clientSecret,
            ]);

        if (!$response->successful()) {
            $errorMsg = $response->json('message') 
                ?? $response->json('error.message') 
                ?? $response->json('error') 
                ?? $response->body();

            Log::error("ABDM v0.5 Session Error [{$response->status()}]: {$errorMsg}");
            throw new Exception("ABDM v0.5 Authentication Failed ({$response->status()}): {$errorMsg}");
        }

        $data = $response->json();
        $token = $data['accessToken'] ?? null;
        $expiresIn = (int) ($data['expiresIn'] ?? 1200);

        if (empty($token)) {
            throw new Exception("Invalid response from ABDM Gateway v0.5: accessToken missing.");
        }

        $ttl = max(60, $expiresIn - 60);
        Cache::put($cacheKey, $token, $ttl);

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

        $token = null;
        try {
            $token = $this->getSessionToken();
        } catch (\Throwable $e) {
            Log::warning("ABDM: Could not get session token for cert retrieval: " . $e->getMessage());
        }

        $cert = null;

        // Try 1: ABHA V3 Profile public certificate endpoint
        if ($token) {
            $abhaCertUrl = "{$this->abhaBaseUrl}/v3/profile/public/certificate";
            try {
                Log::info("ABDM: Fetching public certificate from {$abhaCertUrl}");
                $res = Http::timeout(15)->withHeaders([
                    'REQUEST-ID' => (string) Str::uuid(),
                    'TIMESTAMP' => $this->getIsoTimestamp(),
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->get($abhaCertUrl);

                if ($res->successful()) {
                    $cert = $this->extractCertificateFromResponse($res->body(), $res->json());
                } else {
                    Log::warning("ABDM: ABHA cert endpoint returned {$res->status()}: " . $res->body());
                }
            } catch (\Throwable $e) {
                Log::warning("ABDM: Failed fetching from ABHA cert endpoint: " . $e->getMessage());
            }
        }

        // Try 2: Gateway V3 certs endpoint
        if (empty($cert) && $token) {
            $gwV3CertsUrl = "{$this->gatewayBaseUrl}/gateway/v3/certs";
            try {
                Log::info("ABDM: Fetching certs from Gateway V3: {$gwV3CertsUrl}");
                $res = Http::timeout(15)->withHeaders($this->getStandardHeaders($token))->get($gwV3CertsUrl);
                if ($res->successful()) {
                    $cert = $this->extractCertificateFromResponse($res->body(), $res->json());
                } else {
                    Log::warning("ABDM: Gateway V3 certs returned {$res->status()}: " . $res->body());
                }
            } catch (\Throwable $e) {
                Log::warning("ABDM: Failed fetching from Gateway V3 certs: " . $e->getMessage());
            }
        }

        // Try 3: Gateway V0.5 /certs fallback (publicly accessible, highly reliable)
        if (empty($cert)) {
            $gwV05Url = "https://dev.abdm.gov.in/gateway/v0.5/certs";
            try {
                Log::info("ABDM: Fetching certs from fallback: {$gwV05Url}");
                $res = Http::timeout(15)->withHeaders([
                    'Accept' => 'application/json',
                ])->get($gwV05Url);

                if ($res->successful()) {
                    $cert = $this->extractCertificateFromResponse($res->body(), $res->json());
                }
            } catch (\Throwable $e) {
                Log::warning("ABDM: Failed fetching from fallback certs: " . $e->getMessage());
            }
        }

        if (empty($cert)) {
            throw new Exception("Public key certificate not found in ABDM response.");
        }

        // Format and validate certificate/public key with OpenSSL
        $cert = $this->formatCertForOpenssl($cert);

        // Cache for 24 hours
        Cache::put($cacheKey, $cert, now()->addHours(24));
        return $cert;
    }

    /**
     * Parse raw response body / JSON to extract public key or x509 cert.
     */
    protected function extractCertificateFromResponse(string $body, mixed $json = null): ?string
    {
        if (is_array($json)) {
            // Check direct string fields
            if (!empty($json['publicKey']) && is_string($json['publicKey'])) {
                return $json['publicKey'];
            }
            if (!empty($json['certificate']) && is_string($json['certificate'])) {
                return $json['certificate'];
            }
            if (!empty($json['cert']) && is_string($json['cert'])) {
                return $json['cert'];
            }

            // Check JWKS format: {"keys": [{"x5c": ["..."]}, ...]}
            if (!empty($json['keys']) && is_array($json['keys'])) {
                foreach ($json['keys'] as $keyObj) {
                    if (!empty($keyObj['x5c'][0])) {
                        return (string) $keyObj['x5c'][0];
                    }
                    if (!empty($keyObj['publicKey'])) {
                        return (string) $keyObj['publicKey'];
                    }
                    if (!empty($keyObj['cert'])) {
                        return (string) $keyObj['cert'];
                    }
                }
            }

            // Check array of keys directly: [{"x5c": [...]}]
            if (isset($json[0]['x5c'][0])) {
                return (string) $json[0]['x5c'][0];
            }
            if (isset($json[0]['publicKey'])) {
                return (string) $json[0]['publicKey'];
            }
        }

        $trimmed = trim($body);
        if (str_contains($trimmed, 'BEGIN CERTIFICATE') || str_contains($trimmed, 'BEGIN PUBLIC KEY')) {
            return $trimmed;
        }

        return null;
    }

    /**
     * Ensure certificate is wrapped in valid PEM format that OpenSSL can consume.
     */
    protected function formatCertForOpenssl(string $rawCert): string
    {
        $clean = trim($rawCert);
        if (str_contains($clean, 'BEGIN CERTIFICATE') || str_contains($clean, 'BEGIN PUBLIC KEY')) {
            return $clean;
        }

        $b64 = preg_replace('/\s+/', '', $clean);

        // Try CERTIFICATE PEM first (for x5c)
        $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($b64, 64, "\n") . "-----END CERTIFICATE-----\n";
        if (openssl_pkey_get_public($certPem)) {
            return $certPem;
        }

        // Try PUBLIC KEY PEM (for raw RSA public key)
        $pubPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($b64, 64, "\n") . "-----END PUBLIC KEY-----\n";
        if (openssl_pkey_get_public($pubPem)) {
            return $pubPem;
        }

        return $pubPem;
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
            'TIMESTAMP' => $this->getIsoTimestamp(),
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
     * Send outbound V3 callback to ABDM Gateway with exact standard headers (Bearer, Content-Type, X-CM-ID).
     *
     * @param string $url
     * @param array $payload
     * @return Response
     * @throws Exception
     */
    public function sendGatewayV3Callback(string $url, array $payload): Response
    {
        $accessToken = $this->getSessionToken();

        $response = Http::timeout(15)->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'X-CM-ID' => $this->cmId,
        ])->post($url, $payload);

        if ($response->status() === 401) {
            Log::warning("ABDM Callback 401: Refreshing session token and retrying...");
            $freshToken = $this->getSessionToken(true);
            $response = Http::timeout(15)->withHeaders([
                'Authorization' => 'Bearer ' . $freshToken,
                'Content-Type' => 'application/json',
                'X-CM-ID' => $this->cmId,
            ])->post($url, $payload);
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
