<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OpenLibrary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "Approving all libraries created by admins...\n\n";

// Get all admin user IDs
$adminIds = User::where('isadmin', true)->pluck('id')->toArray();

if (empty($adminIds)) {
    echo "No admin users found.\n";
    exit(0);
}

echo "Found " . count($adminIds) . " admin users\n";

// Update all libraries created by admins
$updated = OpenLibrary::whereIn('creator_id', $adminIds)
    ->where('approval_status', '!=', 'approved')
    ->update([
        'is_approved' => true,
        'approval_status' => 'approved',
        'approval_date' => now(),
        'updated_at' => now()
    ]);

echo "Approved {$updated} libraries created by admins.\n";

// Also approve libraries with no creator_id (legacy data)
$legacyUpdated = OpenLibrary::whereNull('creator_id')
    ->where('approval_status', '!=', 'approved')
    ->update([
        'is_approved' => true,
        'approval_status' => 'approved',
        'approval_date' => now(),
        'updated_at' => now()
    ]);

echo "Approved {$legacyUpdated} legacy libraries (no creator).\n";

echo "\nDone! Total approved: " . ($updated + $legacyUpdated) . " libraries\n";
