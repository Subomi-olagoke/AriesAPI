<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HireSessionSignaling implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sessionId;
    public $fromUserId;
    public $toUserId;
    public $signalData;
    public $type;

    /**
     * Create a new event instance.
     * 
     * @param string $sessionId The hire session ID
     * @param string $fromUserId The user sending the signal
     * @param string $toUserId The user receiving the signal
     * @param mixed $signalData The signaling data (offer, answer, etc.)
     * @param string $type The type of signal ('rtc' or 'ice')
     */
    public function __construct(string $sessionId, string $fromUserId, string $toUserId, $signalData, string $type = 'rtc')
    {
        $this->sessionId = $sessionId;
        $this->fromUserId = $fromUserId;
        $this->toUserId = $toUserId;
        $this->signalData = $signalData;
        $this->type = $type;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Broadcast to a specific user within a session
            new PrivateChannel('hire-session.' . $this->sessionId . '.user.' . $this->toUserId),
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
            'session_id' => $this->sessionId,
            'from_user_id' => $this->fromUserId,
            'to_user_id' => $this->toUserId,
            'signal_data' => $this->signalData,
            'type' => $this->type,
            'timestamp' => now()->toIso8601String()
        ];
    }
    
    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return $this->type === 'ice' ? 'ice.candidate' : 'rtc.signal';
    }
}
