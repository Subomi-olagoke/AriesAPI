<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Cloudinary\Cloudinary;

class CloudinaryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Cloudinary::class, function ($app) {
            // Hardcoded Cloudinary credentials
            // API Key: 629585269241426
            // API Secret: BgyO8HM53l3u1e4D5GtGJM8EZks
            // Cloud Name: duimsumox
            $cloudinaryUrl = "cloudinary://629585269241426:BgyO8HM53l3u1e4D5GtGJM8EZks@duimsumox";
            return new Cloudinary($cloudinaryUrl);
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