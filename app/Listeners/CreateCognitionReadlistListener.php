<?php

namespace App\Listeners;

use App\Services\CognitionService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateCognitionReadlistListener implements ShouldQueue
{
    use InteractsWithQueue;

    protected $cognitionService;

    /**
     * Create the event listener.
     */
    public function __construct(CognitionService $cognitionService)
    {
        $this->cognitionService = $cognitionService;
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        try {
            // Create the user's Cognition readlist
            $readlist = $this->cognitionService->getCognitionReadlist($event->user);
            
            Log::info('Created Cognition readlist for new user', [
                'user_id' => $event->user->id,
                'readlist_id' => $readlist->id
            ]);
            
            // Schedule a job to populate the readlist after a short delay
            // to allow the user to set up their profile and preferences
            \Illuminate\Support\Facades\Queue::later(
                now()->addHours(24),
                new \App\Jobs\PopulateCognitionReadlist($event->user)
            );
        } catch (\Exception $e) {
            Log::error('Failed to create Cognition readlist for new user', [
                'user_id' => $event->user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}