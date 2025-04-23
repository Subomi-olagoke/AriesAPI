<?php

namespace App\Providers;

use App\Providers\CogniServiceProvider;
use App\Services\ContentModerationService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(CogniServiceProvider::class);
        
        $this->app->singleton(ContentModerationService::class, function ($app) {
            return new ContentModerationService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}