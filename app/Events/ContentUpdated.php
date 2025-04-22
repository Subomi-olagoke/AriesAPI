<?php

namespace App\Events;

use App\Models\CollaborativeContent;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $content;
    public $channel_id;
    public $space_id;
    public $user;
    public $type;
    public $data;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\CollaborativeContent  $content
     * @param  string  $channel_id
     * @param  string  $space_id
     * @param  \App\Models\User  $user
     * @param  string  $type
     * @param  array  $data
     * @return void
     */
    public function __construct(CollaborativeContent $content, string $channel_id, string $space_id, User $user, string $type, array $data = [])
    {
        $this->content = $content;
        $this->channel_id = $channel_id;
        $this->space_id = $space_id;
        $this->user = $user;
        $this->type = $type; // e.g., 'update', 'create', 'delete'
        $this->data = $data;
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
            'content_id' => $this->content->id,
            'channel_id' => $this->channel_id,
            'space_id' => $this->space_id,
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'type' => $this->type,
            'version' => $this->content->version,
            'data' => $this->data,
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
        return 'content.updated';
    }
}