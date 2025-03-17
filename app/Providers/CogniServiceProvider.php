<?php

namespace App\Providers;

use App\Services\CogniService;
use Illuminate\Support\ServiceProvider;

class CogniServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CogniService::class, function ($app) {
            return new CogniService();
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