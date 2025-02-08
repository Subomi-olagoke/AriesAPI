<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClassEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $liveClass;

    public function __construct($liveClass)
    {
        $this->liveClass = $liveClass;
    }

    public function broadcastOn()
    {
        return new Channel('live-class.'.$this->liveClass->id);
    }
}
