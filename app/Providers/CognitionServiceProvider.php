<?php

namespace App\Providers;

use App\Services\CognitionService;
use App\Services\CogniService;
use Illuminate\Support\ServiceProvider;

class CognitionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CognitionService::class, function ($app) {
            return new CognitionService(
                $app->make(CogniService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Do nothing in boot to prevent database access before migrations are run
    }
}