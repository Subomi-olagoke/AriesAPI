<?php

// This script fixes the is_admin references in ReportController.php
// Run with: php update_report_controller.php

require __DIR__.'/vendor/autoload.php';

$file = __DIR__.'/app/Http/Controllers/ReportController.php';
$content = file_get_contents($file);

// Replace all instances of is_admin with isAdmin
$updatedContent = str_replace('is_admin', 'isAdmin', $content);

// Write back to file
file_put_contents($file, $updatedContent);

echo "Updated ReportController.php - replaced all instances of 'is_admin' with 'isAdmin'\n";