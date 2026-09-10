<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\LicenseService;

class CheckSoftwareLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow access if license is valid
        if (LicenseService::isValid()) {
            return $next($request);
        }

        // Allow access to login/logout/activation pages
        $path = $request->path();
        if (
            str_contains($path, 'login') || 
            str_contains($path, 'logout') || 
            str_contains($path, 'software-activation') ||
            str_contains($path, 'livewire') // Allow livewire components for the activation page itself
        ) {
            return $next($request);
        }

        // Check if user is logged in
        $user = auth()->user();
        if ($user) {
            // Logged-in users get redirected to the activation page
            return redirect('/admin/software-activation');
        }

        // Guests get a 403 Forbidden message
        abort(403, 'This software is not activated or the license has expired. Please contact the administrator.');
    }
}
