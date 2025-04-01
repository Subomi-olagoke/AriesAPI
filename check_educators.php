<?php
// Load Laravel environment
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Profile;

// Check how many educators exist
$educators = User::where('role', 'educator')->get();
echo "Found " . count($educators) . " educators in the database.\n";

// Check the exact role values that exist
$distinctRoles = User::distinct()->pluck('role')->toArray();
echo "Distinct role values in the database: " . implode(", ", array_filter($distinctRoles)) . "\n";

// List a few educators if they exist
if (count($educators) > 0) {
    echo "\nSample educators:\n";
    foreach ($educators->take(5) as $educator) {
        echo "- {$educator->username} (ID: {$educator->id}, Role: {$educator->role})\n";
    }
}

// Check profiles table 
$profiles = Profile::all();
echo "\nFound " . count($profiles) . " profiles in the database.\n";

// Check if hire_rate and hire_currency columns exist
try {
    $columns = \Schema::getColumnListing('profiles');
    echo "Columns in profiles table: " . implode(", ", $columns) . "\n";
} catch (\Exception $e) {
    echo "Error checking columns: " . $e->getMessage() . "\n";
}

