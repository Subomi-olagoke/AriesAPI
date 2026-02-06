<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class NgrokCommand extends Command {
	protected $signature = 'ngrok';

	protected $description = 'Start Ngrok to expose the local server to the internet';

	public function handle() {
		$this->info('Starting Ngrok...');

		$ngrokAuthToken = config('app.ngrok_auth_token');

		// $process = new Process(["ngrok", "http", "-authtoken={$ngrokAuthToken}", "http://localhost:8000"]);
		$process = new Process([
			'ngrok',
			'http',
			'-authtoken=' . $ngrokAuthToken,
			'8080',
		]);

		$process->setTimeout(0);
		$process->start();

		$ngrokUrl = trim($process->getOutput());

		// Implement Ngrok logic here

		$this->info('Ngrok is running. Public URL: ' . $ngrokUrl);
	}
}
