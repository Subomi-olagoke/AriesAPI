<?php

namespace App\Providers;

use App\Events\ChatMessage;
use App\Events\LiveClassChatMessage;
use App\Events\MessageSent;
use App\Events\UserJoinedClass;
use App\Listeners\AwardPointsListener;
use App\Listeners\SendMessageNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
            AwardPointsListener::class.'@handleUserRegistered',
            // Removed CreateCognitionReadlistListener to avoid missing class at deploy time
        ],
        Login::class => [
            AwardPointsListener::class.'@handleUserLogin',
        ],
        MessageSent::class => [
            SendMessageNotification::class,
            AwardPointsListener::class.'@handleMessageSent',
        ],
        ChatMessage::class => [
            AwardPointsListener::class.'@handleChannelMessage',
        ],
        LiveClassChatMessage::class => [
            AwardPointsListener::class.'@handleLiveClassMessage',
        ],
        UserJoinedClass::class => [
            AwardPointsListener::class.'@handleUserJoinedClass',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // 
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}