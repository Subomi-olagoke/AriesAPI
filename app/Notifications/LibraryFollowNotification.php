<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LibraryFollowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $follower;
    protected $library;

    /**
     * Create a new notification instance.
     */
    public function __construct($follower, $library)
    {
        $this->follower = $follower;
        $this->library = $library;
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
            'message' => "{$this->follower->username} started following your library \"{$this->library->name}\".",
            'sender_name' => "{$this->follower->first_name} {$this->follower->last_name}",
            'avatar' => $this->follower->avatar,
            'follower_id' => $this->follower->id,
            'follower_username' => $this->follower->username,
            'library_id' => $this->library->id,
            'library_name' => $this->library->name,
            'notification_type' => 'library_follow'
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable)
    {
        return [
            'message' => "{$this->follower->username} started following your library \"{$this->library->name}\".",
            'sender_name' => "{$this->follower->first_name} {$this->follower->last_name}",
            'avatar' => $this->follower->avatar,
            'follower_id' => $this->follower->id,
            'follower_username' => $this->follower->username,
            'library_id' => $this->library->id,
            'library_name' => $this->library->name,
        ];
    }
}
