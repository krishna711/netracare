<?php

use App\Http\Controllers\PublicController;
use App\Http\Controllers\PrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'index']);
Route::get('/pay', [PublicController::class, 'pay']);
Route::post('/pay', [PublicController::class, 'storePay']);

Route::prefix('print')->group(function () {
    Route::get('/patient/{id}', [PrintController::class, 'printPatient']);
    Route::get('/patient/history/{id}', [PrintController::class, 'printPatientHistory']);
    Route::get('/patient/details/{id}/{aid}', [PrintController::class, 'printPatientDetails']);
    Route::get('/patient/card/{id}/{aid}', [PrintController::class, 'printEyeCard']);
    Route::get('/receipt/{id}', [PrintController::class, 'printReceipt']);
    Route::get('/ipd/discharge/{id}/{aid}', [PrintController::class, 'printIpdDischarge']);
    Route::get('/patient/abha-card/{id}', [PrintController::class, 'printAbhaCard']);
});

Route::get('/abdm/fhir/{id}/{type?}', [\App\Http\Controllers\AbdmFhirController::class, 'export'])->name('abdm.fhir.export');

// ABDM Gateway Webhooks (without /api prefix)
use App\Http\Controllers\AbdmWebhookController;

Route::prefix('v3/hip')->group(function () {
    Route::match(['post', 'get', 'options', 'head'], '/patient/share', [AbdmWebhookController::class, 'handlePatientShare']);
    Route::match(['post', 'get', 'options', 'head'], '/patient/share/', [AbdmWebhookController::class, 'handlePatientShare']);
    Route::match(['post', 'get', 'options', 'head'], '/patient/running-token/status', [AbdmWebhookController::class, 'handleRunningTokenStatus']);
    Route::match(['post', 'get', 'options', 'head'], '/patient/running-token/status/', [AbdmWebhookController::class, 'handleRunningTokenStatus']);
    Route::match(['post', 'get', 'options', 'head'], '/running-token/status', [AbdmWebhookController::class, 'handleRunningTokenStatus']);
    Route::match(['post', 'get', 'options', 'head'], '/running-token/status/', [AbdmWebhookController::class, 'handleRunningTokenStatus']);
    Route::match(['post', 'get', 'options', 'head'], '/token/status', [AbdmWebhookController::class, 'handleRunningTokenStatus']);
    Route::match(['post', 'get', 'options', 'head'], '/token/status/', [AbdmWebhookController::class, 'handleRunningTokenStatus']);

    Route::post('/patient/care-context/discover', [AbdmWebhookController::class, 'handleCareContextDiscover']);
    Route::post('/link/care-context/init', [AbdmWebhookController::class, 'handleLinkInit']);
    Route::post('/link/care-context/confirm', [AbdmWebhookController::class, 'handleLinkConfirm']);
    Route::post('/health-information/request', [AbdmWebhookController::class, 'handleHealthInfoRequest']);
    Route::post('/consent/notify', [AbdmWebhookController::class, 'handleConsentNotify']);
});

Route::prefix('v0.5')->group(function () {
    Route::post('/care-contexts/discover', [AbdmWebhookController::class, 'handleCareContextDiscover']);
    Route::post('/link/care-contexts/init', [AbdmWebhookController::class, 'handleLinkInit']);
    Route::post('/link/care-contexts/confirm', [AbdmWebhookController::class, 'handleLinkConfirm']);
    Route::post('/health-information/hip/request', [AbdmWebhookController::class, 'handleHealthInfoRequest']);
    Route::post('/consents/hip/notify', [AbdmWebhookController::class, 'handleConsentNotify']);
});

// ABDM Scan & Share - Register all variations of profile share
$profileShareRoutes = [
    '/v3/hip/patient/share',
    '/v1.0/patients/profile/share',
    '/v1/patients/profile/share',
    '/patients/profile/share',
    '/patient/share',
    '/hip/patient/share',
    '/v0.5/patients/profile/share',
    '/api/v3/hip/patient/share',
    '/api/v1.0/patients/profile/share',
    '/api/v1/patients/profile/share',
    '/api/patients/profile/share',
    '/api/patient/share',
    '/api/hip/patient/share',
];

foreach ($profileShareRoutes as $pRoute) {
    Route::match(['post', 'get', 'options', 'head'], $pRoute, [AbdmWebhookController::class, 'handlePatientShare']);
    Route::match(['post', 'get', 'options', 'head'], $pRoute . '/', [AbdmWebhookController::class, 'handlePatientShare']);
}

// Running Token Status variants (both with and without /api)
$webRunningTokenRoutes = [
    '/running-token/status',
    '/patient/running-token/status',
    '/token/status',
    '/patient-share/v3/running-token/status',
    '/v3/hip/running-token/status',
    '/v3/hip/patient/running-token/status',
    '/api/running-token/status',
    '/api/patient/running-token/status',
    '/api/token/status',
    '/api/patient-share/v3/running-token/status',
    '/api/v3/hip/running-token/status',
    '/api/v3/hip/patient/running-token/status',
];

foreach ($webRunningTokenRoutes as $wRoute) {
    Route::match(['post', 'get', 'options', 'head'], $wRoute, [AbdmWebhookController::class, 'handleRunningTokenStatus']);
    Route::match(['post', 'get', 'options', 'head'], $wRoute . '/', [AbdmWebhookController::class, 'handleRunningTokenStatus']);
}