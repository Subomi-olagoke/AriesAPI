<?php

namespace App\Providers;

use App\Services\ExaTrendService;
use Illuminate\Support\ServiceProvider;

class TrendingTopicsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExaTrendService::class, function ($app) {
            return new ExaTrendService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}