<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class HireRequestNotification extends Notification
{
    use Queueable;

    protected $client;
    protected $message;

    /**
     * Create a new notification instance.
     */
    public function __construct($client, $message = null)
    {
        $this->client = $client;
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     * Removed 'mail' from array to disable email notifications
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->client->name . ' has sent you a hire request.',
            'client_id' => $this->client->id,
            'message_content' => $this->message,
        ];
    }
}