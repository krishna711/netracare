<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class LicenseService
{
    private static string $secret = 'NETRACARE_SECRET_KEY_2026_xYz!';
    private static string $licenseFile = '.netracare.lic';
    private static string $installationIdFile = '.netracare.installation_id';

    private static function getLicensePath(): string
    {
        // Store in storage/app (outside public web root, hidden filename)
        return storage_path('app/' . self::$licenseFile);
    }

    private static function getInstallationIdPath(): string
    {
        return storage_path('app/' . self::$installationIdFile);
    }

    private static function getOrCreateInstallationId(): string
    {
        $path = self::getInstallationIdPath();
        if (is_readable($path)) {
            $existing = trim((string) file_get_contents($path));
            if ($existing !== '') {
                return $existing;
            }
        }

        try {
            $id = bin2hex(random_bytes(16));
            @file_put_contents($path, $id);
            return $id;
        } catch (\Throwable $e) {
            return 'UNKNOWN-HWID-1234';
        }
    }

    public static function getHardwareId(): string
    {
        $overrideHwid = trim((string) env('NETRACARE_HWID', ''));
        if ($overrideHwid !== '') {
            return $overrideHwid;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec('getmac', $output);
            if (isset($output[3])) {
                $mac = strtok(trim($output[3]), ' ');
                if (!empty($mac) && strlen($mac) > 5) {
                    return $mac;
                }
            }

            return 'UNKNOWN-HWID-1234';
        }

        $productUuidPath = '/sys/class/dmi/id/product_uuid';
        if (is_readable($productUuidPath)) {
            $uuid = trim((string) file_get_contents($productUuidPath));
            if ($uuid !== '' && $uuid !== '00000000-0000-0000-0000-000000000000') {
                return $uuid;
            }
        }

        foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $machineIdPath) {
            if (is_readable($machineIdPath)) {
                $machineId = trim((string) file_get_contents($machineIdPath));
                if ($machineId !== '') {
                    return $machineId;
                }
            }
        }

        $netDir = '/sys/class/net';
        if (is_dir($netDir)) {
            foreach (scandir($netDir) ?: [] as $iface) {
                if ($iface === '.' || $iface === '..' || $iface === 'lo') {
                    continue;
                }

                $addrPath = $netDir . '/' . $iface . '/address';
                if (!is_readable($addrPath)) {
                    continue;
                }

                $mac = strtolower(trim((string) file_get_contents($addrPath)));
                if (
                    $mac !== '' &&
                    $mac !== '00:00:00:00:00:00' &&
                    preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac)
                ) {
                    return $mac;
                }
            }
        }

        return self::getOrCreateInstallationId();
    }

    public static function generateLicensePayload(string $hwid, string $licenseTo, int $days = 365): string
    {
        $expiryDate = now()->addDays($days)->toDateString();
        $signature = hash('sha256', $hwid . $licenseTo . $expiryDate . self::$secret);

        $payload = [
            'license_to'  => $licenseTo,
            'expiry_date' => $expiryDate,
            'signature'   => $signature,
        ];

        return base64_encode(json_encode($payload));
    }

    public static function decodeKey(string $key): ?array
    {
        $decoded = base64_decode(trim($key));
        if (!$decoded) return null;

        $data = json_decode($decoded, true);
        if (!is_array($data) || !isset($data['license_to'], $data['expiry_date'], $data['signature'])) {
            return null;
        }

        return $data;
    }

    private static function readLicenseFile(): ?string
    {
        $path = self::getLicensePath();
        if (!file_exists($path)) return null;
        return trim(file_get_contents($path));
    }

    private static function writeLicenseFile(string $key): void
    {
        file_put_contents(self::getLicensePath(), $key);
    }

    public static function isValid(): bool
    {
        return Cache::remember('license_valid', 60, function () {
            $key = self::readLicenseFile();
            if (!$key) return false;

            $data = self::decodeKey($key);
            if (!$data) return false;

            $hwid = self::getHardwareId();
            $expectedSignature = hash('sha256', $hwid . $data['license_to'] . $data['expiry_date'] . self::$secret);

            if ($data['signature'] !== $expectedSignature) return false;
            if (Carbon::parse($data['expiry_date'])->isPast()) return false;

            return true;
        });
    }

    public static function getLicenseInfo(): ?array
    {
        $key = self::readLicenseFile();
        if (!$key) return null;
        return self::decodeKey($key);
    }

    public static function activate(string $key): bool
    {
        $data = self::decodeKey($key);
        if (!$data) return false;

        $hwid = self::getHardwareId();
        $expectedSignature = hash('sha256', $hwid . $data['license_to'] . $data['expiry_date'] . self::$secret);

        if ($data['signature'] === $expectedSignature && !Carbon::parse($data['expiry_date'])->isPast()) {
            self::writeLicenseFile(trim($key));
            Cache::forget('license_valid');
            return true;
        }

        return false;
    }
}
