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
    protected $signature = 'live-classes:cleanup {--days=1 : Number of days after end date to wait before cleanup} {--hours=1 : Number of hours past scheduled time to wait before cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired and overdue live classes and their related data from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $hours = (int) $this->option('hours');
        
        $this->info("Starting cleanup of live classes...");
        $this->info("- Classes that ended more than {$days} day(s) ago");
        $this->info("- Classes that passed scheduled time more than {$hours} hour(s) ago");
        
        $results = LiveClass::cleanupAll($days, $hours);
        
        $totalCleaned = $results['total_cleaned'];
        $expiredCleaned = $results['expired_cleaned'];
        $overdueCleaned = $results['overdue_cleaned'];
        
        if ($totalCleaned > 0) {
            $this->info("Successfully cleaned up {$totalCleaned} live class(es):");
            $this->line("  - {$expiredCleaned} expired class(es)");
            $this->line("  - {$overdueCleaned} overdue class(es)");
        } else {
            $this->info("No live classes found to clean up.");
        }
        
        return 0;
    }
}