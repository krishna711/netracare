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
    Route::post('/patient/share', [AbdmWebhookController::class, 'handlePatientShare']);
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

Route::prefix('v1.0')->group(function () {
    Route::post('/patients/profile/share', [AbdmWebhookController::class, 'handlePatientShare']);
});