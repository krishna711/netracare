<?php

namespace App\Services\Abdm;

use Exception;
use Illuminate\Support\Facades\Log;

class AbdmCryptoService
{
    protected AbdmClient $client;

    public function __construct(AbdmClient $client)
    {
        $this->client = $client;
    }

    /**
     * Format raw public key string into a standard PEM format.
     */
    public function formatPublicKey(string $rawKey): string
    {
        $clean = trim($rawKey);
        if (str_contains($clean, 'BEGIN PUBLIC KEY') || str_contains($clean, 'BEGIN CERTIFICATE')) {
            return $clean;
        }

        // Clean any existing newlines or whitespace
        $clean = preg_replace('/\s+/', '', $clean);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($clean, 64, "\n") . "-----END PUBLIC KEY-----";
    }

    /**
     * Encrypt sensitive plain text using ABDM RSA OAEP (SHA-1).
     *
     * @param string $data Plain text (e.g. Aadhaar number, OTP, mobile)
     * @param string|null $overrideCert Optional certificate/public key
     * @return string Base64 encoded encrypted string
     * @throws Exception
     */
    public function encrypt(string $data, ?string $overrideCert = null): string
    {
        $certString = $overrideCert ?: $this->client->getPublicCertificate();
        if (empty($certString)) {
            throw new Exception("ABDM Public Certificate could not be retrieved for encryption.");
        }

        $pem = $this->formatPublicKey($certString);
        $publicKey = openssl_pkey_get_public($pem);

        if (!$publicKey) {
            // Try formatting as CERTIFICATE if PUBLIC KEY format fails
            $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(preg_replace('/\s+/', '', $certString), 64, "\n") . "-----END CERTIFICATE-----";
            $publicKey = openssl_pkey_get_public($certPem);
        }

        if (!$publicKey) {
            $error = openssl_error_string();
            Log::error("ABDM Crypto: Failed to load public key: " . $error);
            throw new Exception("Failed to load ABDM public key certificate: " . ($error ?: 'Invalid PEM format'));
        }

        $encrypted = '';
        $success = openssl_public_encrypt($data, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);

        if (!$success) {
            $error = openssl_error_string();
            Log::error("ABDM Crypto: Encryption failed: " . $error);
            throw new Exception("ABDM RSA Encryption failed: " . ($error ?: 'Unknown OpenSSL error'));
        }

        return base64_encode($encrypted);
    }
}
