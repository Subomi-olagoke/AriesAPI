<?php

namespace App\Events;

use App\Models\HireSession;
use App\Models\HireSessionParticipant;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HireSessionUserJoined implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $hireSession;
    public $participant;
    public $user;

    /**
     * Create a new event instance.
     */
    public function __construct(HireSession $hireSession, User $user, HireSessionParticipant $participant)
    {
        $this->hireSession = $hireSession;
        $this->user = $user;
        $this->participant = $participant;
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
            'participant_id' => $this->participant->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->first_name . ' ' . $this->user->last_name,
                'username' => $this->user->username,
                'avatar' => $this->user->avatar,
                'role' => $this->user->role
            ],
            'role' => $this->participant->role,
            'preferences' => $this->participant->preferences,
            'joined_at' => $this->participant->joined_at->toIso8601String()
        ];
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'user.joined';
    }
}
