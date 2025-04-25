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
        
        // Set upload limits for PHP
        ini_set('upload_max_filesize', '100M');
        ini_set('post_max_size', '100M');
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');
    }
}