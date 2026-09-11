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
});
