<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $liveClass;

    /**
     * Create a new event instance.
     *
     * @param $liveClass
     * @return void
     */
    public function __construct($liveClass)
    {
        $this->liveClass = $liveClass;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PresenceChannel('live-class.'.$this->liveClass->id);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'class_id' => $this->liveClass->id,
            'meeting_id' => $this->liveClass->meeting_id,
            'status' => $this->liveClass->status,
            'ended_at' => $this->liveClass->ended_at,
            'message' => 'Stream has ended'
        ];
    }
}