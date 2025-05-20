<?php

namespace App\Providers;

use App\Services\ExaSearchService;
use Illuminate\Support\ServiceProvider;

class ExaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ExaSearchService::class, function ($app) {
            return new ExaSearchService();
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