<?php

namespace App\Events;

use App\Models\CollaborativeContent;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncOperation implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $operation;
    public $content;
    public $channelId;
    public $spaceId;
    public $user;
    public $isCursor;
    public $syncGroup;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Operation  $operation
     * @param  \App\Models\CollaborativeContent  $content
     * @param  string  $channelId
     * @param  string  $spaceId
     * @param  \App\Models\User  $user
     * @param  bool  $isCursor
     * @param  string|null  $syncGroup
     * @return void
     */
    public function __construct(
        Operation $operation, 
        CollaborativeContent $content, 
        string $channelId, 
        string $spaceId, 
        User $user, 
        bool $isCursor = false,
        string $syncGroup = null
    ) {
        $this->operation = $operation;
        $this->content = $content;
        $this->channelId = $channelId;
        $this->spaceId = $spaceId;
        $this->user = $user;
        $this->isCursor = $isCursor;
        $this->syncGroup = $syncGroup ?? $content->id; // Default to content ID if no specific group
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to both content and space channels
        return [
            new PresenceChannel('collaboration.content.' . $this->content->id),
            new PresenceChannel('collaboration.space.' . $this->spaceId),
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
            'operation_id' => $this->operation->id,
            'content_id' => $this->content->id,
            'channel_id' => $this->channelId,
            'space_id' => $this->spaceId,
            'sync_group' => $this->syncGroup,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username ?? $this->user->name,
                'avatar' => $this->user->avatar
            ],
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
            'is_cursor' => $this->isCursor,
            'content_version' => $this->content->version,
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
        return $this->isCursor ? 'cursor.update' : 'operation.received';
    }
}