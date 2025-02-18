<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserJoinedClass implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $liveClass;
    public $user;
    public $participant;

    public function __construct($liveClass, $user, $participant)
    {
        $this->liveClass = $liveClass;
        $this->user = $user;
        $this->participant = $participant;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('live-class.'.$this->liveClass->id);
    }
}
