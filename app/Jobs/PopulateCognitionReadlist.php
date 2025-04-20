<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\CognitionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PopulateCognitionReadlist implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $maxItems;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, int $maxItems = 5)
    {
        $this->user = $user;
        $this->maxItems = $maxItems;
    }

    /**
     * Execute the job.
     */
    public function handle(CognitionService $cognitionService): void
    {
        try {
            Log::info('Starting to populate Cognition readlist', ['user_id' => $this->user->id]);
            
            $result = $cognitionService->updateCognitionReadlist($this->user, $this->maxItems);
            
            if ($result) {
                Log::info('Successfully populated Cognition readlist', ['user_id' => $this->user->id]);
            } else {
                Log::info('No updates made to Cognition readlist', ['user_id' => $this->user->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to populate Cognition readlist', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);
            
            // Mark the job as failed
            $this->fail($e);
        }
    }
}