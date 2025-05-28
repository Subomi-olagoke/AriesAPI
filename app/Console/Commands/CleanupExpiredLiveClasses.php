<?php

namespace App\Console\Commands;

use App\Models\LiveClass;
use Illuminate\Console\Command;

class CleanupExpiredLiveClasses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live-classes:cleanup {--days=1 : Number of days after end date to wait before cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired live classes and their related data from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        
        $this->info("Starting cleanup of live classes that ended more than {$days} day(s) ago...");
        
        $cleanedCount = LiveClass::cleanupExpired($days);
        
        if ($cleanedCount > 0) {
            $this->info("Successfully cleaned up {$cleanedCount} expired live class(es).");
        } else {
            $this->info("No expired live classes found to clean up.");
        }
        
        return 0;
    }
}