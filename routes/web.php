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