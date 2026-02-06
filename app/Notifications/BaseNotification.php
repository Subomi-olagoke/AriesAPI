<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        // Add 'apn' channel if the user has a device token
        $channels = ['database'];
        
        if (isset($notifiable->device_token) && !empty($notifiable->device_token)) {
            $channels[] = 'apn';
        }
        
        return $channels;
    }
    
    /**
     * Get the APNs representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \NotificationChannels\Apn\ApnMessage
     */
    public function toApn($notifiable)
    {
        // Get notification data
        $data = $this->toArray($notifiable);
        
        // Extract title and body from notification data
        $title = $data['title'] ?? 'Edututor';
        $body = $data['message'] ?? 'You have a new notification';
        
        // Create the APN message
        // For this to work, you need to install the package with:
        // composer require laravel-notification-channels/apn
        return \NotificationChannels\Apn\ApnMessage::create()
            ->badge(1)
            ->title($title)
            ->body($body)
            ->sound('default')
            ->custom($data);
    }
    
    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    abstract public function toArray($notifiable);
}