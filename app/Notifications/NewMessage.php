<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewMessage extends BaseNotification implements ShouldQueue
{
    protected $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        $sender = $this->message->sender;
        
        return [
            'title' => 'New Message',
            'message' => 'New message from ' . $sender->username,
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $sender->id,
            'sender_name' => $sender->username,
            'sender_avatar' => $sender->avatar,
            'message_body' => $this->message->body,
            'preview' => substr($this->message->body, 0, 100),
            'notification_type' => 'new_message'
        ];
    }
}