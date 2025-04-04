<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BroadcastNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $title;
    protected $body;
    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $title, string $body, array $data = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        // Use the parent's via method to get the channels
        // This will automatically include APNs if device_token exists
        return parent::via($notifiable);
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toMail($notifiable)
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ];
    }

    /**
     * Get the APNs representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \NotificationChannels\Apn\ApnMessage
     */
    public function toApn($notifiable)
    {
        \Log::info('Creating APN message for: ' . $notifiable->id . ' with token: ' . ($notifiable->device_token ?? 'none'));
        
        // Optional debugging: show more detailed info about the notification
        \Log::debug('APNs notification details', [
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'device_token' => $notifiable->device_token ?? 'none',
            'token_length' => strlen($notifiable->device_token ?? ''),
            'token_is_hex' => ctype_xdigit($notifiable->device_token ?? ''),
            'app_bundle_id' => config('services.apn.app_bundle_id', env('APNS_APP_BUNDLE_ID')),
            'production_mode' => config('services.apn.production', env('APNS_PRODUCTION')),
        ]);
        
        // Create the message with all required properties for iOS notification display
        $message = \NotificationChannels\Apn\ApnMessage::create()
            ->badge(1)
            ->title($this->title)
            ->body($this->body)
            ->sound('default')
            ->pushType('alert') // Explicitly set push type
            ->contentAvailable(true) // Ensure notification is delivered
            ->mutableContent(true) // Allow for notification modification
            ->priority(10); // High priority for immediate delivery
        
        // Add all data as custom payload
        if (!empty($this->data)) {
            $message->custom($this->data);
        }
        
        return $message;
    }
}