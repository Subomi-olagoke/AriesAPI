<?php

namespace App\Events;

use App\Models\CollaborativeContent;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CollaborationOperation implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $operation;
    public $content;
    public $channel_id;
    public $space_id;
    public $user;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Operation  $operation
     * @param  \App\Models\CollaborativeContent  $content
     * @param  string  $channel_id
     * @param  string  $space_id
     * @param  \App\Models\User  $user
     * @return void
     */
    public function __construct(Operation $operation, CollaborativeContent $content, string $channel_id, string $space_id, User $user)
    {
        $this->operation = $operation;
        $this->content = $content;
        $this->channel_id = $channel_id;
        $this->space_id = $space_id;
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PresenceChannel('collaboration.content.' . $this->content->id);
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'operation_id' => $this->operation->id,
            'content_id' => $this->content->id,
            'channel_id' => $this->channel_id,
            'space_id' => $this->space_id,
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'operation' => [
                'id' => $this->operation->id,
                'type' => $this->operation->type,
                'position' => $this->operation->position,
                'length' => $this->operation->length,
                'text' => $this->operation->text,
                'version' => $this->operation->version,
                'meta' => $this->operation->meta,
                'created_at' => $this->operation->created_at->toIso8601String(),
            ],
            'version' => $this->content->version,
            'timestamp' => now()->toIso8601String()
        ];
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'operation.received';
    }
}