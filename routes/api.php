<?php

use App\Http\Controllers\AbdmWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ABDM API Routes
|--------------------------------------------------------------------------
| These endpoints receive callbacks from the ABDM Gateway (e.g. Scan & Share).
*/

Route::prefix('v3/hip')->group(function () {
    Route::post('/patient/share', [AbdmWebhookController::class, 'handlePatientShare']);
    Route::post('/patient/care-context/discover', [AbdmWebhookController::class, 'handleCareContextDiscover']);
    Route::post('/link/care-context/init', [AbdmWebhookController::class, 'handleLinkInit']);
    Route::post('/link/care-context/confirm', [AbdmWebhookController::class, 'handleLinkConfirm']);
    Route::post('/health-information/request', [AbdmWebhookController::class, 'handleHealthInfoRequest']);
    Route::post('/consent/notify', [AbdmWebhookController::class, 'handleConsentNotify']);
});

// v3 HIU Endpoints (Milestone 3 - Consent Management & Health Data Flow)
Route::prefix('v3/hiu')->group(function () {
    Route::post('/consent/request/on-init', [AbdmWebhookController::class, 'handleConsentOnInit']);
    Route::post('/consent/on-init', [AbdmWebhookController::class, 'handleConsentOnInit']);
    Route::post('/consent/request/on-status', [AbdmWebhookController::class, 'handleConsentOnStatus']);
    Route::post('/consent/on-status', [AbdmWebhookController::class, 'handleConsentOnStatus']);
    Route::post('/consent/notify', [AbdmWebhookController::class, 'handleConsentNotify']);
    Route::post('/consent/request/notify', [AbdmWebhookController::class, 'handleConsentNotify']);
    Route::post('/consent/request/hiu/notify', [AbdmWebhookController::class, 'handleConsentNotify']);
    Route::post('/consent/on-fetch', [AbdmWebhookController::class, 'handleConsentOnFetch']);
    Route::post('/consent/request/on-fetch', [AbdmWebhookController::class, 'handleConsentOnFetch']);
    Route::post('/health-information/on-request', [AbdmWebhookController::class, 'handleHealthInfoOnRequest']);
    Route::post('/data/notification', [AbdmWebhookController::class, 'handleDataNotification']);
});

// v0.5 Legacy endpoints (called by PHR / Gateway in v0.5 mode)
Route::prefix('v0.5')->group(function () {
    Route::post('/care-contexts/discover', [AbdmWebhookController::class, 'handleCareContextDiscover']);
    Route::post('/link/care-contexts/init', [AbdmWebhookController::class, 'handleLinkInit']);
    Route::post('/link/care-contexts/confirm', [AbdmWebhookController::class, 'handleLinkConfirm']);
    Route::post('/health-information/hip/request', [AbdmWebhookController::class, 'handleHealthInfoRequest']);
    Route::post('/consents/hip/notify', [AbdmWebhookController::class, 'handleConsentNotify']);
});

// v1.0 & v3 Scan and share variants (under /api prefix)
Route::prefix('v1.0')->group(function () {
    Route::match(['post', 'get'], '/patients/profile/share', [AbdmWebhookController::class, 'handlePatientShare']);
    Route::match(['post', 'get'], '/patients/profile/share/', [AbdmWebhookController::class, 'handlePatientShare']);
});

Route::prefix('v1')->group(function () {
    Route::match(['post', 'get'], '/patients/profile/share', [AbdmWebhookController::class, 'handlePatientShare']);
    Route::match(['post', 'get'], '/patients/profile/share/', [AbdmWebhookController::class, 'handlePatientShare']);
});

Route::match(['post', 'get'], '/patients/profile/share', [AbdmWebhookController::class, 'handlePatientShare']);
Route::match(['post', 'get'], '/patients/profile/share/', [AbdmWebhookController::class, 'handlePatientShare']);
Route::match(['post', 'get'], '/patient/share', [AbdmWebhookController::class, 'handlePatientShare']);
Route::match(['post', 'get'], '/patient/share/', [AbdmWebhookController::class, 'handlePatientShare']);

