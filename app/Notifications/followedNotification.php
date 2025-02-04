<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class followedNotification extends Notification
{
    use Queueable;
    protected $follower;
    protected $followedUser;



    /**
     * Create a new notification instance.
     */
    public function __construct($follower, $followedUser)
    {

        $this->follower = $follower;
        $this->followedUser = $followedUser;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase() {
        return [
            'message' => "{$this->follower->name} followed you",
            'avatar' => $this->follower->avatar ?? null,
            'follower_id' => $this->follower->id,
            'user_id' => $this->followedUser->id,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
