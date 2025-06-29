<?php

namespace App\Console\Commands;

use App\Models\CourseEnrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredEnrollments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enrollments:cleanup {--minutes=3 : Number of minutes to wait before cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up pending enrollments that have been pending for more than specified minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = $this->option('minutes');
        $cutoffTime = now()->subMinutes($minutes);

        // Find pending enrollments older than the cutoff time
        $expiredEnrollments = CourseEnrollment::where('status', 'pending')
            ->where('created_at', '<', $cutoffTime)
            ->get();

        if ($expiredEnrollments->isEmpty()) {
            $this->info('No expired pending enrollments found.');
            return 0;
        }

        $this->info("Found {$expiredEnrollments->count()} expired pending enrollments.");

        $deletedCount = 0;
        foreach ($expiredEnrollments as $enrollment) {
            try {
                // Log the cleanup for debugging
                Log::info('Cleaning up expired pending enrollment', [
                    'enrollment_id' => $enrollment->id,
                    'user_id' => $enrollment->user_id,
                    'course_id' => $enrollment->course_id,
                    'created_at' => $enrollment->created_at,
                    'pending_duration_minutes' => $enrollment->created_at->diffInMinutes(now())
                ]);

                // Delete the enrollment
                $enrollment->delete();
                $deletedCount++;

                $this->line("Deleted enrollment ID: {$enrollment->id} (User: {$enrollment->user_id}, Course: {$enrollment->course_id})");
            } catch (\Exception $e) {
                Log::error('Failed to delete expired enrollment', [
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage()
                ]);
                $this->error("Failed to delete enrollment ID: {$enrollment->id} - {$e->getMessage()}");
            }
        }

        $this->info("Successfully cleaned up {$deletedCount} expired pending enrollments.");
        return 0;
    }
} 