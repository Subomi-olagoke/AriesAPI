<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CognitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateCognitionReadlists extends Command
{
    protected $signature = 'cognition:update {--user_id= : Specific user ID to update} {--batch=50 : Number of users to process per batch} {--max_items=3 : Maximum items to add per user}';
    protected $description = 'Update Cognition readlists for users with personalized content';

    protected $cognitionService;

    public function __construct(CognitionService $cognitionService)
    {
        parent::__construct();
        $this->cognitionService = $cognitionService;
    }

    public function handle()
    {
        $specificUserId = $this->option('user_id');
        $batchSize = (int)$this->option('batch');
        $maxItems = (int)$this->option('max_items');
        
        if ($specificUserId) {
            $this->updateForSpecificUser($specificUserId, $maxItems);
        } else {
            $this->updateForAllUsers($batchSize, $maxItems);
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Update Cognition readlist for a specific user
     */
    protected function updateForSpecificUser(string $userId, int $maxItems)
    {
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return;
        }
        
        $this->info("Updating Cognition readlist for user: {$user->name} (ID: {$user->id})");
        
        try {
            $result = $this->cognitionService->updateCognitionReadlist($user, $maxItems);
            
            if ($result) {
                $this->info('Successfully updated Cognition readlist.');
            } else {
                $this->warn('No new resources were added to the Cognition readlist.');
            }
        } catch (\Exception $e) {
            $this->error("Error updating Cognition readlist: {$e->getMessage()}");
            Log::error('Error in UpdateCognitionReadlists command for specific user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Update Cognition readlists for all users in batches
     */
    protected function updateForAllUsers(int $batchSize, int $maxItems)
    {
        $this->info("Starting batch update of Cognition readlists for all users (batch size: {$batchSize}, max items: {$maxItems})");
        
        $totalUsers = User::count();
        $processedCount = 0;
        $successCount = 0;
        $skipCount = 0;
        $errorCount = 0;
        
        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->start();
        
        User::query()
            ->select(['id', 'name', 'email', 'created_at'])
            ->chunkById($batchSize, function ($users) use ($maxItems, $progressBar, &$processedCount, &$successCount, &$skipCount, &$errorCount) {
                foreach ($users as $user) {
                    try {
                        // Skip users with less than 1 day of activity (not enough data to profile)
                        if ($user->created_at->diffInDays(now()) < 1) {
                            $skipCount++;
                            $progressBar->advance();
                            $processedCount++;
                            continue;
                        }
                        
                        $result = $this->cognitionService->updateCognitionReadlist($user, $maxItems);
                        
                        if ($result) {
                            $successCount++;
                        } else {
                            $skipCount++;
                        }
                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error('Error updating Cognition readlist in batch', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                    
                    $progressBar->advance();
                    $processedCount++;
                }
            });
        
        $progressBar->finish();
        $this->newLine(2);
        
        $this->info("Cognition readlist update complete:");
        $this->info("- Total users processed: {$processedCount}");
        $this->info("- Successfully updated: {$successCount}");
        $this->info("- Skipped (no updates or new users): {$skipCount}");
        $this->info("- Errors encountered: {$errorCount}");
    }
}