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
	}

	protected $commands = [
		// Existing commands...
		\App\Console\Commands\NgrokCommand::class,
		\App\Console\Commands\BackfillPostShareKeys::class,
		\App\Console\Commands\SeedAlexPointsSystem::class,
		\App\Console\Commands\UpdateCognitionReadlists::class,
	];

	/**
	 * Register the commands for the application.
	 */
	protected function commands(): void {
		$this->load(__DIR__ . '/Commands');

		require base_path('routes/console.php');
	}
}