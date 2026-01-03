<?php

namespace App\Providers;

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

        // Log Redis connection status on boot (to verify connection for debugging)
        // We do this inside a try-catch to ensure app doesn't crash if Redis is down
        try {
            // Only check occasionally or on debug to avoid ping overhead on every single request
            // For now, we'll check it to reassure deployment status
            if (!app()->runningInConsole()) {
                \Illuminate\Support\Facades\Cache::getRedis()->ping();
                \Illuminate\Support\Facades\Log::info("✅ App Boot: Redis ping successful (Request: " . request()->path() . ")");
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("❌ App Boot: Redis connection failed: " . $e->getMessage());
        }
    }
}