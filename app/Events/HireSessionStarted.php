<?php

namespace App\Events;

use App\Models\HireSession;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HireSessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $hireSession;
    public $startedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(HireSession $hireSession, User $startedBy)
    {
        $this->hireSession = $hireSession;
        $this->startedBy = $startedBy;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('hire-session.' . $this->hireSession->id),
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
            'session_id' => $this->hireSession->id,
            'meeting_id' => $this->hireSession->meeting_id,
            'started_at' => $this->hireSession->video_session_started_at->toIso8601String(),
            'started_by' => [
                'id' => $this->startedBy->id,
                'name' => $this->startedBy->first_name . ' ' . $this->startedBy->last_name,
                'role' => $this->startedBy->role
            ],
            'settings' => $this->hireSession->video_session_settings
        ];
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'video.started';
    }
}
