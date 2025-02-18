<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RTCSignaling implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $classId;
    public $fromUserId;
    public $toUserId;
    public $signalData;

    public function __construct($classId, $fromUserId, $toUserId, $signalData)
    {
        $this->classId = $classId;
        $this->fromUserId = $fromUserId;
        $this->toUserId = $toUserId;
        $this->signalData = $signalData;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('live-class.'.$this->classId);
    }
}
