<?php
// Load Laravel environment
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// 1. Add columns to the profiles table if they don't exist
$columns = DB::select("PRAGMA table_info(profiles)");
$columnNames = array_map(function($col) { return $col->name; }, $columns);

$missingColumns = [];
if (\!in_array('qualifications', $columnNames)) $missingColumns[] = 'qualifications JSON NULL';
if (\!in_array('teaching_style', $columnNames)) $missingColumns[] = 'teaching_style VARCHAR(255) NULL';
if (\!in_array('availability', $columnNames)) $missingColumns[] = 'availability JSON NULL';
if (\!in_array('hire_rate', $columnNames)) $missingColumns[] = 'hire_rate DECIMAL(10,2) NULL';
if (\!in_array('hire_currency', $columnNames)) $missingColumns[] = 'hire_currency VARCHAR(3) NULL';
if (\!in_array('social_links', $columnNames)) $missingColumns[] = 'social_links JSON NULL';

if (\!empty($missingColumns)) {
    echo "Adding missing columns to profiles table...\n";
    foreach ($missingColumns as $column) {
        $sql = "ALTER TABLE profiles ADD COLUMN $column";
        try {
            DB::statement($sql);
            echo "Added: $column\n";
        } catch (\Exception $e) {
            echo "Error adding column ($column): " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "All required columns exist in profiles table.\n";
}

// 2. Update user with ID = 1 to be an educator (for testing)
try {
    DB::table('users')
        ->where('id', '=', '1b4e38c5-e636-4649-a204-6a89e6a92505')
        ->update(['role' => 'educator']);
    echo "Updated user ID 1b4e38c5-e636-4649-a204-6a89e6a92505 to educator role.\n";
} catch (\Exception $e) {
    echo "Error updating user: " . $e->getMessage() . "\n";
}

// 3. Add or update profile with hire_rate = 75 and hire_currency = USD
try {
    $userExists = DB::table('users')
        ->where('id', '=', '1b4e38c5-e636-4649-a204-6a89e6a92505')
        ->exists();
    
    if ($userExists) {
        $profileExists = DB::table('profiles')
            ->where('user_id', '=', '1b4e38c5-e636-4649-a204-6a89e6a92505')
            ->exists();
        
        if ($profileExists) {
            DB::table('profiles')
                ->where('user_id', '=', '1b4e38c5-e636-4649-a204-6a89e6a92505')
                ->update([
                    'hire_rate' => 75,
                    'hire_currency' => 'USD'
                ]);
            echo "Updated profile for user 1b4e38c5-e636-4649-a204-6a89e6a92505 with hire_rate=75 USD.\n";
        } else {
            DB::table('profiles')->insert([
                'user_id' => '1b4e38c5-e636-4649-a204-6a89e6a92505',
                'hire_rate' => 75,
                'hire_currency' => 'USD',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "Created profile for user 1b4e38c5-e636-4649-a204-6a89e6a92505 with hire_rate=75 USD.\n";
        }
    } else {
        echo "User with ID 1b4e38c5-e636-4649-a204-6a89e6a92505 not found.\n";
    }
} catch (\Exception $e) {
    echo "Error updating profile: " . $e->getMessage() . "\n";
}

echo "Done\!\n";
