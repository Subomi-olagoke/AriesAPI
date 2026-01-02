<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $follower;

    /**
     * Create a new notification instance.
     */
    public function __construct($follower)
    {
        $this->follower = $follower;
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

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->follower->username} started following you.",
            'sender_name' => "{$this->follower->first_name} {$this->follower->last_name}",
            'avatar' => $this->follower->avatar,
            'follower_id' => $this->follower->id,
            'follower_username' => $this->follower->username,
            'notification_type' => 'follow'
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable)
    {
        return [
            'message' => "{$this->follower->username} started following you.",
            'sender_name' => "{$this->follower->first_name} {$this->follower->last_name}",
            'avatar' => $this->follower->avatar,
            'follower_id' => $this->follower->id,
            'follower_username' => $this->follower->username,
        ];
    }
}
