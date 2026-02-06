<?php

use App\Models\User;
use App\Models\Follow;

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Find the user to be followed
$targetUsername = 'subomi olagoke';
$targetUser = User::where('username', $targetUsername)->first();

if (!$targetUser) {
    echo "Target user with username '$targetUsername' not found.\n";
    exit(1);
}

$targetId = $targetUser->id;

// Get all users except the target
$users = User::where('id', '!=', $targetId)->get();

$count = 0;
foreach ($users as $user) {
    // Check if already following
    $already = Follow::where('user_id', $user->id)
        ->where('followeduser', $targetId)
        ->exists();
    if ($already) {
        echo "User {$user->username} already follows {$targetUsername}\n";
        continue;
    }
    // Create follow
    Follow::create([
        'user_id' => $user->id,
        'followeduser' => $targetId,
    ]);
    echo "User {$user->username} now follows {$targetUsername}\n";
    $count++;
}
echo "Done. $count users now follow $targetUsername.\n"; 