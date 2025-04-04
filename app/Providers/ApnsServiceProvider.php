<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\ChannelManager;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Apn\ApnAdapter;
use NotificationChannels\Apn\Exceptions\ConnectionFailed;

class ApnsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the APN channel with Laravel's notification system
        $this->app->extend('Illuminate\Notifications\ChannelManager', function (ChannelManager $service, $app) {
            $service->extend('apn', function ($app) {
                $config = $app['config']['services.apn'];

                try {
                    $options = [
                        'key_id' => $config['key_id'],
                        'team_id' => $config['team_id'],
                        'app_bundle_id' => $config['app_bundle_id'],
                        'production' => $config['production'] ?? false,
                    ];

                    // If using key content from environment variable
                    if (isset($config['private_key_content']) && $config['private_key_content']) {
                        $privateKeyPath = storage_path('app/apns_private_key.p8');
                        
                        // Create storage directory if it doesn't exist
                        if (!file_exists(dirname($privateKeyPath))) {
                            mkdir(dirname($privateKeyPath), 0755, true);
                        }
                        
                        // Decode and save the key content to a temporary file
                        $keyContent = base64_decode($config['private_key_content']);
                        if (!file_exists($privateKeyPath) || md5_file($privateKeyPath) !== md5($keyContent)) {
                            file_put_contents($privateKeyPath, $keyContent);
                        }
                        
                        $options['private_key_path'] = $privateKeyPath;
                    } else if (isset($config['private_key_path'])) {
                        $options['private_key_path'] = $config['private_key_path'];
                    }

                    $client = new ApnAdapter($options);
                    
                    return new ApnChannel($client);
                } catch (\Exception $e) {
                    \Log::error('APNs initialization error: ' . $e->getMessage());
                    throw new ConnectionFailed('Could not initialize APNs: ' . $e->getMessage());
                }
            });
            
            return $service;
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