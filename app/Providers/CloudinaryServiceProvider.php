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
            $cloudinaryUrl = "cloudinary://789173963938137:xAJe8VzAp_ZhWyy-SICwZy3o1Ps@dnm8itso6";
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