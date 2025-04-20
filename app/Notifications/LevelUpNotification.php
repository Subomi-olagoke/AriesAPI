<?php

namespace App\Notifications;

use App\Models\AlexPointsLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LevelUpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $level;

    /**
     * Create a new notification instance.
     */
    public function __construct(AlexPointsLevel $level)
    {
        $this->level = $level;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Congratulations on Leveling Up!')
            ->line('Congratulations! You\'ve reached level ' . $this->level->level . ': ' . $this->level->name)
            ->line($this->level->description)
            ->action('View Your Profile', url('/profile'))
            ->line('Thank you for being an active member of our community!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'level' => $this->level->level,
            'level_name' => $this->level->name,
            'description' => $this->level->description,
            'rewards' => $this->level->rewards,
            'type' => 'level_up'
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toBroadcast($notifiable)
    {
        return [
            'level' => $this->level->level,
            'level_name' => $this->level->name,
            'description' => $this->level->description,
            'type' => 'level_up'
        ];
    }
}