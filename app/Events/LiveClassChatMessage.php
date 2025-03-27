<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\LiveClassChat;

class LiveClassChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chatMessage;

    /**
     * Create a new event instance.
     */
    public function __construct(LiveClassChat $chatMessage)
    {
        $this->chatMessage = $chatMessage;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return new PresenceChannel('live-class.'.$this->chatMessage->live_class_id);
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'message' => [
                'id' => $this->chatMessage->id,
                'message' => $this->chatMessage->message,
                'type' => $this->chatMessage->type,
                'metadata' => $this->chatMessage->metadata,
                'created_at' => $this->chatMessage->created_at,
                'user' => [
                    'id' => $this->chatMessage->user->id,
                    'name' => $this->chatMessage->user->first_name . ' ' . $this->chatMessage->user->last_name,
                    'username' => $this->chatMessage->user->username,
                    'avatar' => $this->chatMessage->user->avatar,
                    'role' => $this->chatMessage->user->role
                ]
            ]
        ];
    }
}