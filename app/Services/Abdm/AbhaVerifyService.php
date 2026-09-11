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
    public function verifyLoginOtp(string $txnId, string $otp, string $authType = 'mobile'): array
    {
        $cleanOtp = trim($otp);
        $encryptedOtp = $this->crypto->encrypt($cleanOtp);
        $url = "{$this->client->getAbhaBaseUrl()}/v3/profile/login/verify";

        $verifyScope = $authType === 'aadhaar' ? 'aadhaar-verify' : 'mobile-verify';

        $payload = [
            'scope' => ['abha-login', $verifyScope],
            'authData' => [
                'authMethods' => ['otp'],
                'otp' => [
                    'txnId' => $txnId,
                    'otpValue' => $encryptedOtp,
                ],
            ],
        ];

        Log::info("ABDM ABHA Verify: Verifying login OTP for txnId {$txnId} with scope {$verifyScope}");

        $response = $this->client->sendRequest('POST', $url, $payload);

        // If scope mismatch, automatically retry with alternate verification scope
        if (!$response->successful() && str_contains($response->body(), 'Invalid Scope')) {
            $altScope = $verifyScope === 'mobile-verify' ? 'aadhaar-verify' : 'mobile-verify';
            $payload['scope'] = ['abha-login', $altScope];
            Log::info("ABDM ABHA Verify: Retrying with alternate scope {$altScope}");
            $response = $this->client->sendRequest('POST', $url, $payload);
        }

        if (!$response->successful()) {
            $err = $response->json('message') 
                ?? $response->json('error.message') 
                ?? $response->json('details.0.message') 
                ?? $response->body();
            throw new Exception("OTP Verification Failed ({$response->status()}): {$err}");
        }

        $data = $response->json();
        Log::info("ABDM ABHA Verify response: ", ['data' => $data]);

        $token = $data['token'] ?? $data['jwtToken'] ?? ($data['tokens']['token'] ?? null);

        // Decode JWT token payload if available to extract sub / abhaNumber
        $jwtPayload = [];
        if (!empty($token) && substr_count($token, '.') >= 2) {
            $parts = explode('.', $token);
            $decoded = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (is_array($decoded)) {
                $jwtPayload = $decoded;
            }
        }

        // Account list or profile
        $account = $data['accounts'][0] ?? [];
        $profile = [];
        if (!empty($token)) {
            try {
                $profile = $this->getProfile($token);
            } catch (\Throwable $e) {
                Log::warning("Could not auto-fetch profile after verify: " . $e->getMessage());
            }
        }

        $effective = array_merge($jwtPayload, $data, $account, $profile);
        $abhaNumber = $effective['ABHANumber'] 
            ?? $effective['abhaNumber'] 
            ?? $effective['sub'] 
            ?? null;

        $abhaAddress = $effective['preferredAbhaAddress'] 
            ?? (is_array($effective['phrAddress'] ?? null) ? $effective['phrAddress'][0] : ($effective['phrAddress'] ?? null))
            ?? $effective['abhaAddress'] 
            ?? ($abhaNumber ? str_replace('-', '', $abhaNumber) . '@abdm' : null);

        $firstName = $effective['firstName'] ?? '';
        $lastName = $effective['lastName'] ?? '';
        $name = $effective['name'] ?? trim("{$firstName} {$lastName}");

        return [
            'status' => 'success',
            'xToken' => $token,
            'profile' => $effective,
            'abhaNumber' => $abhaNumber,
            'abhaAddress' => $abhaAddress,
            'name' => $name ?: 'ABHA User',
            'mobile' => $effective['mobile'] ?? null,
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
