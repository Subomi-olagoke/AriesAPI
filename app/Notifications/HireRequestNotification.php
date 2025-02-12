<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Hire Request')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->client->name . ' has sent you a hire request.')
            ->line($this->message ? 'Message: "' . $this->message . '"' : '')
            ->action('View Request', url('/hire-requests'))
            ->line('Thank you for using our platform!');
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
