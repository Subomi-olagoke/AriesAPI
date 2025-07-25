<?php
// Update app/Console/Kernel.php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {
	/**
	 * Define the application's command schedule.
	 */
	protected function schedule(Schedule $schedule): void {
		// $schedule->command('inspire')->hourly();
		
		// Update Cognition readlists for all users once a week (every Monday at 1am)
		$schedule->command('cognition:update --batch=100 --max_items=5')->weekly()->mondays()->at('01:00');
		
		// Generate libraries from popular posts once a week (every Tuesday at 2am)
		$schedule->command('libraries:generate-from-posts --days=7 --min-posts=10')->weekly()->tuesdays()->at('02:00');
		
		// Clean up expired live classes daily at 3am
		$schedule->command('live-classes:cleanup --days=1 --hours=1')->daily()->at('03:00');
		
		// Clean up overdue live classes every hour (more frequent for better user experience)
		$schedule->command('live-classes:cleanup --days=999 --hours=1')->hourly();
		
		// Clean up expired pending enrollments every 5 minutes
		$schedule->command('enrollments:cleanup --minutes=3')->everyFiveMinutes();
		
		// Refresh trending topics data every 6 hours
		$schedule->command('trends:refresh')->everySixHours();
	}

	protected $commands = [
		// Existing commands...
		\App\Console\Commands\NgrokCommand::class,
		\App\Console\Commands\BackfillPostShareKeys::class,
		\App\Console\Commands\SeedAlexPointsSystem::class,
		\App\Console\Commands\UpdateCognitionReadlists::class,
		\App\Console\Commands\GenerateLibrariesFromPosts::class,
		\App\Console\Commands\GenerateOpenLibraries::class,
		\App\Console\Commands\CleanupExpiredLiveClasses::class,
		\App\Console\Commands\EnsureAllUsersHaveCognitionReadlist::class,
		\App\Console\Commands\CreateTestReadlist::class,
		\App\Console\Commands\RefreshTrendsCommand::class,
		\App\Console\Commands\CleanupExpiredEnrollments::class,
		\App\Console\Commands\FollowSubomiCommand::class,
	];

	/**
	 * Register the commands for the application.
	 */
	protected function commands(): void {
		$this->load(__DIR__ . '/Commands');

		require base_path('routes/console.php');
	}
}