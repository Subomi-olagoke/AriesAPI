<?php

namespace App\Notifications;

use App\Models\Mention;
use Illuminate\Contracts\Queue\ShouldQueue;

class MentionNotification extends BaseNotification implements ShouldQueue
{
    protected $mention;

    /**
     * Create a new notification instance.
     */
    public function __construct(Mention $mention)
    {
        $this->mention = $mention;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        $mentionableType = class_basename($this->mention->mentionable_type);
        $contentType = strtolower($mentionableType);
        
        return [
            'title' => 'You were mentioned',
            'message' => "{$this->mention->mentionedByUser->username} mentioned you in a {$contentType}.",
            'mention_id' => $this->mention->id,
            'mentionable_id' => $this->mention->mentionable_id,
            'mentionable_type' => $contentType,
            'mentioned_by' => [
                'id' => $this->mention->mentionedByUser->id,
                'username' => $this->mention->mentionedByUser->username,
                'avatar' => $this->mention->mentionedByUser->avatar,
            ],
            'notification_type' => 'mention'
        ];
    }
}