<?php

// Script to update all educator profiles to have a hire_rate of 75 USD

// Load Laravel environment
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Profile;

// Get all educators
$educators = User::where('role', 'educator')->get();
$count = 0;

echo "Updating educator profiles to have a hire_rate of 75 USD...\n";

foreach ($educators as $educator) {
    // Get or create profile
    $profile = Profile::firstOrCreate(['user_id' => $educator->id]);
    
    // Update hire_rate and hire_currency
    $profile->hire_rate = 75;
    $profile->hire_currency = 'USD';
    $profile->save();
    
    $count++;
    echo "Updated profile for {$educator->username} (ID: {$educator->id})\n";
}

echo "Done\! Updated {$count} educator profiles.\n";
