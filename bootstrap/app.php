<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'v3/*',
            'api/v3/*',
            'v0.5/*',
            'api/v0.5/*',
            'v1.0/*',
            'api/v1.0/*',
            'v1/*',
            'api/v1/*',
            'patients/*',
            'api/patients/*',
            'patient/*',
            'api/patient/*',
            'share/*',
            'api/share/*',
            'running-token/*',
            'api/running-token/*',
            'token/*',
            'api/token/*',
            'patient-share/*',
            'api/patient-share/*',
            'hip/*',
            'api/hip/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
