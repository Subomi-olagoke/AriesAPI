<?php

// This script manually grants admin access to a user with username 'subomi'
// Run with: php grant_admin_access.php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "Searching for user 'subomi'...\n";

$user = User::where('username', 'subomi')->first();

if ($user) {
    echo "User found with ID: {$user->id}\n";
    
    // Update user with admin privileges
    $user->is_admin = true;
    $user->save();
    
    echo "Admin privileges granted to user 'subomi'!\n";
} else {
    echo "User 'subomi' not found in the database.\n";
    echo "Please check the username and try again.\n";
}