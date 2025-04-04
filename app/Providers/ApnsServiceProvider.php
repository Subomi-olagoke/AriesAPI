<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\ChannelManager;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Apn\ApnVapidAdapter;
use NotificationChannels\Apn\TokenProvider\FileTokenProvider;
use NotificationChannels\Apn\TokenProvider\TokenProviderInterface;

class ApnsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(TokenProviderInterface::class, function ($app) {
            $config = $app['config']['services.apn'];
            
            // If using key content from environment variable
            if (isset($config['private_key_content']) && $config['private_key_content']) {
                $privateKeyPath = storage_path('app/apns_private_key.p8');
                
                // Decode and save the key content to a temporary file
                if (!file_exists($privateKeyPath) || md5_file($privateKeyPath) !== md5(base64_decode($config['private_key_content']))) {
                    file_put_contents($privateKeyPath, base64_decode($config['private_key_content']));
                }
                
                return new FileTokenProvider(
                    $config['key_id'],
                    $privateKeyPath,
                    $config['team_id'],
                    $config['app_bundle_id']
                );
            }
            
            // Fallback to using the key path if content is not provided
            return new FileTokenProvider(
                $config['key_id'],
                env('APNS_PRIVATE_KEY_PATH'),
                $config['team_id'],
                $config['app_bundle_id']
            );
        });
        
        // Register the APN channel with Laravel's notification system
        $this->app->extend('Illuminate\Notifications\ChannelManager', function (ChannelManager $service, $app) {
            $service->extend('apn', function ($app) {
                return new ApnChannel(
                    $app->make(TokenProviderInterface::class),
                    $app->make(ApnVapidAdapter::class),
                    $app['config']['services.apn.production'] ?? false
                );
            });
            
            return $service;
        });
        
        // Register the ApnVapidAdapter
        $this->app->singleton(ApnVapidAdapter::class, function ($app) {
            return new ApnVapidAdapter();
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