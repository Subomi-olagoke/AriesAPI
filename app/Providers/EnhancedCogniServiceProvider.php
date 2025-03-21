<?php

namespace App\Providers;

use App\Services\CogniService;
use App\Services\EnhancedCogniService;
use Illuminate\Support\ServiceProvider;

class EnhancedCogniServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(EnhancedCogniService::class, function ($app) {
            return new EnhancedCogniService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}