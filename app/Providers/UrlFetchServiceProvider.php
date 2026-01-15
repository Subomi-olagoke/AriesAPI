<?php

namespace App\Providers;

use App\Services\UrlFetchService;
use Illuminate\Support\ServiceProvider;

class UrlFetchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(UrlFetchService::class, function ($app) {
            return new UrlFetchService(
                $app->make(\App\Services\AIService::class),
                $app->make(\App\Services\ExaSearchService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}