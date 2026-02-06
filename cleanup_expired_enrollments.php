<?php
/**
 * Standalone script to clean up expired pending enrollments
 * Run this script to immediately clean up any pending enrollments older than 3 minutes
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CourseEnrollment;
use Illuminate\Support\Facades\Log;

echo "Starting cleanup of expired pending enrollments...\n";

$minutes = 3; // Clean up enrollments pending for more than 3 minutes
$cutoffTime = now()->subMinutes($minutes);

// Find pending enrollments older than the cutoff time
$expiredEnrollments = CourseEnrollment::where('status', 'pending')
    ->where('created_at', '<', $cutoffTime)
    ->get();

if ($expiredEnrollments->isEmpty()) {
    echo "No expired pending enrollments found.\n";
    exit(0);
}

echo "Found {$expiredEnrollments->count()} expired pending enrollments.\n";

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

        echo "Deleted enrollment ID: {$enrollment->id} (User: {$enrollment->user_id}, Course: {$enrollment->course_id})\n";
    } catch (Exception $e) {
        Log::error('Failed to delete expired enrollment', [
            'enrollment_id' => $enrollment->id,
            'error' => $e->getMessage()
        ]);
        echo "Failed to delete enrollment ID: {$enrollment->id} - {$e->getMessage()}\n";
    }
}

echo "Successfully cleaned up {$deletedCount} expired pending enrollments.\n"; 