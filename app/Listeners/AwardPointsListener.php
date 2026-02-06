<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use App\Events\MessageSent;
use App\Events\ChatMessage;
use App\Events\LiveClassChatMessage;
use App\Events\UserJoinedClass;

/**
 * Stub listener to satisfy event bindings without awarding points.
 * All handlers are no-ops to avoid runtime binding errors.
 */
class AwardPointsListener
{
    public function handleUserRegistered(Registered $event): void
    {
        // Intentionally no-op
    }

    public function handleUserLogin(Login $event): void
    {
        // Intentionally no-op
    }

    public function handleMessageSent(MessageSent $event): void
    {
        // Intentionally no-op
    }

    public function handleChannelMessage(ChatMessage $event): void
    {
        // Intentionally no-op
    }

    public function handleLiveClassMessage(LiveClassChatMessage $event): void
    {
        // Intentionally no-op
    }

    public function handleUserJoinedClass(UserJoinedClass $event): void
    {
        // Intentionally no-op
    }
}

