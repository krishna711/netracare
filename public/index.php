<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Raw ABDM Gateway Request Interceptor & Logger
// Ensures EVERY incoming request from ABDM Gateway or related to patient/share/consent is logged with full fidelity,
// regardless of routing, 404, 405, 419, or LiteSpeed/Hostinger quirks.
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isAbdmRequest = preg_match('/(share|patient|care-context|consent|abdm|v3\/hip|v1\.0|v0\.5)/i', $requestUri)
    || isset($_SERVER['HTTP_REQUEST_ID'])
    || isset($_SERVER['HTTP_X_CM_ID']);

if ($isAbdmRequest) {
    $rawInput = file_get_contents('php://input');
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logLine = sprintf(
        "[%s] IP: %s | METHOD: %s | URI: %s | HEADERS: %s | BODY: %s\n",
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        $requestUri,
        json_encode([
            'REQUEST-ID' => $_SERVER['HTTP_REQUEST_ID'] ?? null,
            'TIMESTAMP' => $_SERVER['HTTP_TIMESTAMP'] ?? null,
            'X-CM-ID' => $_SERVER['HTTP_X_CM_ID'] ?? null,
            'AUTHORIZATION' => isset($_SERVER['HTTP_AUTHORIZATION']) ? substr($_SERVER['HTTP_AUTHORIZATION'], 0, 20) . '...' : null,
            'CONTENT-TYPE' => $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? null,
            'USER-AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]),
        substr($rawInput, 0, 4000)
    );
    @file_put_contents($logDir . '/abdm_gateway.log', $logLine, FILE_APPEND | LOCK_EX);
    @file_put_contents($logDir . '/laravel.log', "[ABDM INCOMING RAW] " . $logLine, FILE_APPEND | LOCK_EX);
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());

