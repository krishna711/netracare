<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            // Fetch all settings once and share them globally to all views (perfect for Print templates)
            $hospitalSettings = \App\Models\Setting::pluck('value', 'key')->toArray();
            \Illuminate\Support\Facades\View::share('hospitalSettings', $hospitalSettings);
        } catch (\Exception $e) {
            // Failsafe catch for when database hasn't migrated yet
        }
    }
}
