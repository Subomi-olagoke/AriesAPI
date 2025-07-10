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
            // Hardcode the Cloudinary URL instead of using config
            $cloudinaryUrl = "cloudinary://747141434628117:6mb0FviHFXjJsit1CoG32G515rE@digjzuwdf";
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