<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\GPTSearchService;

class GPTSearchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(GPTSearchService::class, function ($app) {
            return new GPTSearchService();
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