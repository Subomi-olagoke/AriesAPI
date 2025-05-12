<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ApiClientService;

class ApiClientServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ApiClientService::class, function ($app) {
            return new ApiClientService();
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