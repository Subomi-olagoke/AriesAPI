<?php

namespace App\Listeners;

use App\Events\ChatMessage;
use App\Events\LiveClassChatMessage;
use App\Events\MessageSent;
use App\Events\UserJoinedClass;
use App\Services\AlexPointsService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardPointsListener implements ShouldQueue
{
    protected $pointsService;

    /**
     * Create the event listener.
     */
    public function __construct(AlexPointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }

    /**
     * Handle user registration events.
     */
    public function handleUserRegistered(Registered $event)
    {
        $this->pointsService->awardPointsForAction(
            $event->user,
            'user_registered'
        );
    }

    /**
     * Handle daily login events.
     */
    public function handleUserLogin(Login $event)
    {
        // Check if user already logged in today
        $user = $event->user;
        $loggedInToday = $user->pointsTransactions()
            ->where('action_type', 'daily_login')
            ->whereDate('created_at', now()->toDateString())
            ->exists();

        if (!$loggedInToday) {
            $this->pointsService->awardPointsForAction(
                $user,
                'daily_login'
            );
        }
    }

    /**
     * Handle message sent events.
     */
    public function handleMessageSent(MessageSent $event)
    {
        $user = $event->message->sender;
        
        if ($user) {
            $this->pointsService->awardPointsForAction(
                $user,
                'send_message',
                'message',
                $event->message->id
            );
        }
    }

    /**
     * Handle channel message events.
     */
    public function handleChannelMessage(ChatMessage $event)
    {
        $this->pointsService->awardPointsForAction(
            $event->user,
            'send_channel_message',
            'channel_message',
            $event->message->id
        );
    }

    /**
     * Handle live class chat message events.
     */
    public function handleLiveClassMessage(LiveClassChatMessage $event)
    {
        $this->pointsService->awardPointsForAction(
            $event->user,
            'send_class_message',
            'live_class_message',
            $event->message->id
        );
    }

    /**
     * Handle user joined class events.
     */
    public function handleUserJoinedClass(UserJoinedClass $event)
    {
        $this->pointsService->awardPointsForAction(
            $event->user,
            'join_live_class',
            'live_class',
            $event->liveClass->id
        );
    }
}