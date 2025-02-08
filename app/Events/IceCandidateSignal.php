<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IceCandidateSignal implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $classId;
    public $fromUserId;
    public $toUserId;
    public $candidate;

    public function __construct($classId, $fromUserId, $toUserId, $candidate)
    {
        $this->classId = $classId;
        $this->fromUserId = $fromUserId;
        $this->toUserId = $toUserId;
        $this->candidate = $candidate;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('live-class.'.$this->classId);
    }
}
