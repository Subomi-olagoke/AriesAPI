<?php

// This script fixes the is_admin references in AdminUserController.php
// Run with: php update_admin_user_controller.php

require __DIR__.'/vendor/autoload.php';

$file = __DIR__.'/app/Http/Controllers/AdminUserController.php';
$content = file_get_contents($file);

// Replace all instances of is_admin with isAdmin
$updatedContent = str_replace('is_admin', 'isAdmin', $content);

// Write back to file
file_put_contents($file, $updatedContent);

echo "Updated AdminUserController.php - replaced all instances of 'is_admin' with 'isAdmin'\n";