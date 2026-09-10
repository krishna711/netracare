<?php

namespace App\Services\Abdm;

use Exception;
use Illuminate\Support\Facades\Log;

class AbhaVerifyService
{
    protected AbdmClient $client;
    protected AbdmCryptoService $crypto;

    public function __construct(AbdmClient $client, AbdmCryptoService $crypto)
    {
        $this->client = $client;
        $this->crypto = $crypto;
    }

    /**
     * Search existing ABHA accounts by mobile number or ABHA number.
     *
     * @param string $mobileOrAbha
     * @return array List of matched ABHA accounts
     * @throws Exception
     */
    public function searchAbha(string $mobileOrAbha): array
    {
        $input = trim($mobileOrAbha);
        $encrypted = $this->crypto->encrypt($input);

        $url = "{$this->client->getAbhaBaseUrl()}/v3/profile/account/abha/search";
        $payload = [
            'scope' => ['search-abha'],
            'mobile' => $encrypted,
        ];

        Log::info("ABDM ABHA Verify: Searching ABHA accounts");

        $response = $this->client->sendRequest('POST', $url, $payload);

        if (!$response->successful()) {
            $err = $response->json('message') ?? $response->body();
            throw new Exception("ABHA Search Failed ({$response->status()}): {$err}");
        }

        $data = $response->json();
        return is_array($data) ? $data : ['data' => $data];
    }

    /**
     * Request OTP to verify an existing ABHA user.
     *
     * @param string $loginHint 'abha-number', 'mobile', or 'aadhaar'
     * @param string $loginId Plain text identifier
     * @param string $otpSystem 'abdm' or 'aadhaar'
     * @return array Contains txnId
     * @throws Exception
     */
    public function requestLoginOtp(string $loginHint, string $loginId, string $otpSystem = 'abdm'): array
    {
        $encryptedId = $this->crypto->encrypt(trim($loginId));
        $url = "{$this->client->getAbhaBaseUrl()}/v3/profile/login/request/otp";

        $payload = [
            'scope' => ['abha-login', $otpSystem === 'aadhaar' ? 'aadhaar-verify' : 'mobile-verify'],
            'loginHint' => $loginHint,
            'loginId' => $encryptedId,
            'otpSystem' => $otpSystem,
        ];

        Log::info("ABDM ABHA Verify: Requesting login OTP for {$loginHint}");

        $response = $this->client->sendRequest('POST', $url, $payload);

        if (!$response->successful()) {
            $err = $response->json('message') 
                ?? $response->json('error.message') 
                ?? $response->body();
            throw new Exception("Verification OTP Request Failed ({$response->status()}): {$err}");
        }

        $data = $response->json();
        return [
            'status' => 'success',
            'txnId' => $data['txnId'] ?? null,
            'message' => $data['message'] ?? 'Verification OTP sent.',
            'raw' => $data,
        ];
    }

    /**
     * Verify OTP and retrieve verified user profile & token.
     *
     * @param string $txnId
     * @param string $otp
     * @return array Profile details and xToken
     * @throws Exception
     */
    public function verifyLoginOtp(string $txnId, string $otp): array
    {
        $cleanOtp = trim($otp);
        $encryptedOtp = $this->crypto->encrypt($cleanOtp);
        $url = "{$this->client->getAbhaBaseUrl()}/v3/profile/login/verify";

        $payload = [
            'authData' => [
                'authMethods' => ['otp'],
                'otp' => [
                    'txnId' => $txnId,
                    'otpValue' => $encryptedOtp,
                ],
            ],
            'scope' => ['abha-login'],
        ];

        Log::info("ABDM ABHA Verify: Verifying login OTP for txnId {$txnId}");

        $response = $this->client->sendRequest('POST', $url, $payload);

        if (!$response->successful()) {
            $err = $response->json('message') ?? $response->body();
            throw new Exception("OTP Verification Failed ({$response->status()}): {$err}");
        }

        $data = $response->json();
        $token = $data['token'] ?? $data['jwtToken'] ?? ($data['tokens']['token'] ?? null);

        // Fetch full profile if token available
        $profile = [];
        if (!empty($token)) {
            try {
                $profile = $this->getProfile($token);
            } catch (\Throwable $e) {
                Log::warning("Could not auto-fetch profile after verify: " . $e->getMessage());
            }
        }

        return [
            'status' => 'success',
            'xToken' => $token,
            'profile' => $profile ?: $data,
            'abhaNumber' => $profile['ABHANumber'] ?? $profile['abhaNumber'] ?? ($data['ABHANumber'] ?? null),
            'abhaAddress' => $profile['preferredAbhaAddress'] ?? $profile['abhaAddress'] ?? ($data['preferredAbhaAddress'] ?? null),
            'name' => $profile['name'] ?? ($data['name'] ?? null),
            'mobile' => $profile['mobile'] ?? ($data['mobile'] ?? null),
            'raw' => $data,
        ];
    }

    /**
     * Fetch user profile from /v3/profile/account using X-Token.
     */
    public function getProfile(string $xToken): array
    {
        $url = "{$this->client->getAbhaBaseUrl()}/v3/profile/account";
        $response = $this->client->sendRequest('GET', $url, [], $xToken);

        if (!$response->successful()) {
            throw new Exception("Failed to fetch profile: " . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Fetch official user QR code using X-Token.
     */
    public function getQrCode(string $xToken): string
    {
        $url = "{$this->client->getAbhaBaseUrl()}/v3/profile/account/qrCode";
        $response = $this->client->sendRequest('GET', $url, [], $xToken);

        if (!$response->successful()) {
            throw new Exception("Failed to fetch QR code: " . $response->body());
        }

        return $response->body();
    }
}
