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

class DocumentOperation implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $operation;
    public $content;
    public $channelId;
    public $documentId;
    public $user;
    public $isCursor;

    /**
     * Create a new event instance.
     */
    public function __construct(Operation $operation, CollaborativeContent $content, $channelId, $documentId, User $user, $isCursor = false)
    {
        $this->operation = $operation;
        $this->content = $content;
        $this->channelId = $channelId;
        $this->documentId = $documentId;
        $this->user = $user;
        $this->isCursor = $isCursor;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('document.' . $this->documentId),
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
            'operation' => [
                'id' => $this->operation->id,
                'type' => $this->operation->type,
                'position' => $this->operation->position,
                'length' => $this->operation->length,
                'text' => $this->operation->text,
                'version' => $this->operation->version,
                'meta' => $this->operation->meta,
                'created_at' => $this->operation->created_at
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar
            ],
            'is_cursor' => $this->isCursor,
            'content_version' => $this->content->version
        ];
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'document.operation';
    }
}