<?php

namespace App\Events;

use App\Models\HireSession;
use App\Models\HireSessionDocument;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentShared implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $document;
    public $session;

    /**
     * Create a new event instance.
     */
    public function __construct(HireSessionDocument $document, HireSession $session)
    {
        $this->document = $document;
        $this->session = $session;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('hire-session.' . $this->session->id),
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
                'id' => $this->document->id,
                'title' => $this->document->title,
                'file_type' => $this->document->file_type,
                'file_size' => $this->document->file_size,
                'description' => $this->document->description,
                'shared_at' => $this->document->shared_at->toIso8601String(),
                'download_url' => $this->document->getDownloadUrl(),
                'user' => [
                    'id' => $this->document->user->id,
                    'name' => $this->document->user->name,
                    'avatar' => $this->document->user->avatar
                ]
            ],
            'session_id' => $this->session->id,
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
