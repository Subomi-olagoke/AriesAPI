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
        return \NotificationChannels\Apn\ApnMessage::create()
            ->badge(1)
            ->title($this->title)
            ->body($this->body)
            ->sound('default')
            ->custom($this->data);
    }
}