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
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Use the parent's via method to get the channels
        // This will automatically include APNs if device_token exists
        return parent::via($notifiable);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): array
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
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ];
    }

    /**
     * Get the APNs representation of the notification.
     */
    public function toApn(object $notifiable): array
    {
        return [
            'aps' => [
                'alert' => [
                    'title' => $this->title,
                    'body' => $this->body,
                ],
                'badge' => 1,
                'sound' => 'default',
            ],
            'custom_data' => $this->data,
        ];
    }
}