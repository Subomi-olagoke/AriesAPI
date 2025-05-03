<?php

// This script adds the is_admin column to the users table if it doesn't exist
// and then grants admin access to a user with username 'subomi'
// Run with: php add_admin_column.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "Checking if 'is_admin' column exists in users table...\n";

if (!Schema::hasColumn('users', 'is_admin')) {
    echo "Column 'is_admin' doesn't exist. Adding it now...\n";
    
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('is_admin')->default(false)->after('role');
    });
    
    echo "Column 'is_admin' added successfully.\n";
} else {
    echo "Column 'is_admin' already exists.\n";
}

echo "Searching for user 'subomi'...\n";

// List some users to see what's available
echo "Available users:\n";
$users = User::select('id', 'username', 'email')->limit(5)->get();
foreach ($users as $user) {
    echo "- {$user->username} (ID: {$user->id}, Email: {$user->email})\n";
}

// Try to find the user
$user = User::where('username', 'subomi')->first();

if (!$user) {
    echo "User 'subomi' not found. Would you like to search by email instead? Input the email or type 'no' to exit:\n";
    $email = trim(fgets(STDIN));
    
    if ($email !== 'no') {
        $user = User::where('email', $email)->first();
    }
}

if ($user) {
    echo "User found with ID: {$user->id}\n";
    
    // Update user with admin privileges
    $user->is_admin = true;
    $user->save();
    
    echo "Admin privileges granted to user {$user->username}!\n";
    echo "User can now access the admin dashboard at https://ariesmvp-9903a26b3095.herokuapp.com/admin/dashboard\n";
} else {
    echo "User not found.\n";
    echo "Please specify a user ID to grant admin privileges to: ";
    $userId = trim(fgets(STDIN));
    
    if ($userId) {
        $user = User::find($userId);
        if ($user) {
            $user->is_admin = true;
            $user->save();
            
            echo "Admin privileges granted to user {$user->username}!\n";
            echo "User can now access the admin dashboard at https://ariesmvp-9903a26b3095.herokuapp.com/admin/dashboard\n";
        } else {
            echo "User with ID {$userId} not found.\n";
        }
    }
}