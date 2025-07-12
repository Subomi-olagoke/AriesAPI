<?php

namespace App\Events;

use App\Models\LiveClass;
use App\Models\LiveClassMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $liveClass;
    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(LiveClass $liveClass, LiveClassMessage $message)
    {
        $this->liveClass = $liveClass;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('live-class.' . $this->liveClass->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'live_class_id' => $this->liveClass->id,
            'message' => [
                'id' => $this->message->id,
                'user_id' => $this->message->user_id,
                'username' => $this->message->user->username,
                'first_name' => $this->message->user->first_name,
                'last_name' => $this->message->user->last_name,
                'avatar' => $this->message->user->avatar,
                'message' => $this->message->message,
                'message_type' => $this->message->message_type,
                'created_at' => $this->message->created_at->toISOString()
            ]
        ];
    }
} 