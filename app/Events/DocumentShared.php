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
    public $sourceChannel;
    public $targetChannel;
    public $sharedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(
        CollaborativeSpace $space, 
        Channel $sourceChannel, 
        Channel $targetChannel, 
        User $sharedBy
    ) {
        $this->space = $space;
        $this->channel = $targetChannel; // Set to target channel for backwards compatibility
        $this->sourceChannel = $sourceChannel;
        $this->targetChannel = $targetChannel;
        $this->sharedBy = $sharedBy;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('channel.' . $this->sourceChannel->id),
            new PresenceChannel('channel.' . $this->targetChannel->id),
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
                'title' => $this->space->title,
                'description' => $this->space->description,
                'type' => $this->space->type,
            ],
            'source_channel' => [
                'id' => $this->sourceChannel->id,
                'name' => $this->sourceChannel->name,
            ],
            'target_channel' => [
                'id' => $this->targetChannel->id,
                'name' => $this->targetChannel->name,
            ],
            'shared_by' => [
                'id' => $this->sharedBy->id,
                'name' => $this->sharedBy->name,
                'avatar' => $this->sharedBy->avatar
            ],
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