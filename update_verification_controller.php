<?php

// This script fixes the is_admin references in VerificationController.php
// Run with: php update_verification_controller.php

require __DIR__.'/vendor/autoload.php';

$file = __DIR__.'/app/Http/Controllers/VerificationController.php';
$content = file_get_contents($file);

// Replace all instances of is_admin with isAdmin
$updatedContent = str_replace('is_admin', 'isAdmin', $content);

// Write back to file
file_put_contents($file, $updatedContent);

echo "Updated VerificationController.php - replaced all instances of 'is_admin' with 'isAdmin'\n";