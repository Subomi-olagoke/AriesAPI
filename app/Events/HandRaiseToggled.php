<?php

namespace App\Events;

use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HandRaiseToggled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $liveClass;
    public $user;
    public $handRaised;

    /**
     * Create a new event instance.
     */
    public function __construct(LiveClass $liveClass, User $user, bool $handRaised)
    {
        $this->liveClass = $liveClass;
        $this->user = $user;
        $this->handRaised = $handRaised;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('live-class.' . $this->liveClass->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'live_class_id' => $this->liveClass->id,
            'user_id' => $this->user->id,
            'username' => $this->user->username,
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'avatar' => $this->user->avatar,
            'hand_raised' => $this->handRaised,
            'timestamp' => now()->toISOString()
        ];
    }
} 