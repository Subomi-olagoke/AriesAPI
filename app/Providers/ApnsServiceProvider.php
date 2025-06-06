<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\ChannelManager;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Apn\Exceptions\ConnectionFailed;
use Illuminate\Support\Facades\Config;

class ApnsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the APN channel directly with the app
        $this->app->singleton('notificationchannels.apn.certificate.app', function ($app) {
            $config = $app['config']['services.apn'];
            
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
                
                $config['private_key_path'] = $privateKeyPath;
            }
            
            // Add configuration values to the services.apn config
            $certConfig = [
                'key_id' => $config['key_id'],
                'team_id' => $config['team_id'],
                'app_bundle_id' => $config['app_bundle_id'],
                'private_key_path' => $config['private_key_path'],
                'production' => $config['production'] ?? false,
            ];
            
            // Log the configuration for debugging
            \Log::info('Setting APNs certificate configuration', ['config' => array_merge(
                $certConfig,
                ['private_key_path_exists' => file_exists($config['private_key_path'])]
            )]);
            
            // Set the configuration in both standard locations to ensure compatibility
            Config::set('services.apn.certificate.app', $certConfig);
            
            // Also set it directly in the root for older package versions
            Config::set('apn.certificate.app', $certConfig);
            
            return $config;
        });

        // Register the APN channel with Laravel's notification system
        $this->app->extend('Illuminate\Notifications\ChannelManager', function (ChannelManager $service, $app) {
            $service->extend('apn', function ($app) {
                try {
                    // Make sure the certificate config is registered
                    $app->make('notificationchannels.apn.certificate.app');
                    
                    // Create APNs channel with detailed error handling
                    $channel = $app->make(ApnChannel::class);

                    // Add response listener if available (depends on package version)
                    try {
                        if (method_exists($channel, 'onError')) {
                            $channel->onError(function ($error) {
                                \Log::error('APNs Error', [
                                    'error' => $error->getMessage(),
                                    'device_token' => $error->getDeviceToken(),
                                    'error_code' => $error->getCode(),
                                    'notification' => $error->getNotification()
                                ]);
                            });
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Could not add APNs error handler: ' . $e->getMessage());
                    }

                    return $channel;
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