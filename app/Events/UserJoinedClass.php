<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserJoinedClass implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $liveClass;
    public $user;

    public function __construct($liveClass, $user)
    {
        $this->liveClass = $liveClass;
        $this->user = $user;
    }

    public function broadcastOn()
    {
        return new Channel('live-class.'.$this->liveClass->id);
    }
}
