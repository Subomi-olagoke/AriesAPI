<?php

namespace App\Events;

use App\Models\Channel;
use App\Models\CollaborativeContent;
use App\Models\CollaborativeSpace;
use App\Models\User;
use Illuminate\Broadcasting\Channel as BroadcastChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentShared implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $content;
    public $space;
    public $channel;
    public $sharedBy;
    public $sharedWith;
    public $role;

    /**
     * Create a new event instance.
     */
    public function __construct(
        CollaborativeContent $content, 
        CollaborativeSpace $space, 
        Channel $channel, 
        User $sharedBy, 
        User $sharedWith, 
        string $role
    ) {
        $this->content = $content;
        $this->space = $space;
        $this->channel = $channel;
        $this->sharedBy = $sharedBy;
        $this->sharedWith = $sharedWith;
        $this->role = $role;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->sharedWith->id),
        ];
    }
    
    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'document' => [
                'id' => $this->space->id,
                'content_id' => $this->content->id,
                'title' => $this->space->title,
                'description' => $this->space->description,
                'type' => $this->space->type,
                'content_type' => $this->content->content_type,
                'channel_id' => $this->channel->id,
                'channel_name' => $this->channel->name
            ],
            'shared_by' => [
                'id' => $this->sharedBy->id,
                'name' => $this->sharedBy->name,
                'avatar' => $this->sharedBy->avatar
            ],
            'role' => $this->role,
            'timestamp' => now()->toIso8601String()
        ];
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'document.shared';
    }
}