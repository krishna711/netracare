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

// v0.5 Legacy endpoints (called by PHR / Gateway in v0.5 mode)
Route::prefix('v0.5')->group(function () {
    Route::post('/care-contexts/discover', [AbdmWebhookController::class, 'handleCareContextDiscover']);
    Route::post('/link/care-contexts/init', [AbdmWebhookController::class, 'handleLinkInit']);
    Route::post('/link/care-contexts/confirm', [AbdmWebhookController::class, 'handleLinkConfirm']);
    Route::post('/health-information/hip/request', [AbdmWebhookController::class, 'handleHealthInfoRequest']);
    Route::post('/consents/hip/notify', [AbdmWebhookController::class, 'handleConsentNotify']);
});

// v1.0 Scan and share
Route::prefix('v1.0')->group(function () {
    Route::post('/patients/profile/share', [AbdmWebhookController::class, 'handlePatientShare']);
});
