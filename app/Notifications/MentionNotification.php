<?php

namespace App\Notifications;

use App\Models\Mention;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $mention;

    /**
     * Create a new notification instance.
     */
    public function __construct(Mention $mention)
    {
        $this->mention = $mention;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $mentionableType = class_basename($this->mention->mentionable_type);
        $contentType = strtolower($mentionableType);
        
        return [
            'mention_id' => $this->mention->id,
            'mentionable_id' => $this->mention->mentionable_id,
            'mentionable_type' => $contentType,
            'mentioned_by' => [
                'id' => $this->mention->mentionedByUser->id,
                'username' => $this->mention->mentionedByUser->username,
                'avatar' => $this->mention->mentionedByUser->avatar,
            ],
            'message' => "{$this->mention->mentionedByUser->username} mentioned you in a {$contentType}.",
        ];
    }
}