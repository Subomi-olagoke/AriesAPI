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

class HireSessionEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $hireSession;
    public $endedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(HireSession $hireSession, User $endedBy)
    {
        $this->hireSession = $hireSession;
        $this->endedBy = $endedBy;
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
            'ended_at' => $this->hireSession->video_session_ended_at->toIso8601String(),
            'duration' => $this->hireSession->video_session_started_at->diffInMinutes($this->hireSession->video_session_ended_at),
            'ended_by' => [
                'id' => $this->endedBy->id,
                'name' => $this->endedBy->first_name . ' ' . $this->endedBy->last_name,
                'role' => $this->endedBy->role
            ],
            'recording_url' => $this->hireSession->recording_url
        ];
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'video.ended';
    }
}
