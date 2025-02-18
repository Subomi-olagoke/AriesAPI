<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRTCSignaling implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $signal;
    public $userId;
    public $classId;

    public function __construct($classId, $userId, $signal)
    {
        $this->classId = $classId;
        $this->userId = $userId;
        $this->signal = $signal;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('live-class.'.$this->classId);
    }
}
