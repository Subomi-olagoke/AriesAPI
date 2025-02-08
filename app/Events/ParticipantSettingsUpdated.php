<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantSettingsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $classId;
    public $userId;
    public $settings;

    public function __construct($classId, $userId, $settings)
    {
        $this->classId = $classId;
        $this->userId = $userId;
        $this->settings = $settings;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('live-class.'.$this->classId);
    }
}
