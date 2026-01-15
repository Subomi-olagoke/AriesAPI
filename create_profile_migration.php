<?php
// Load Laravel environment
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Check if columns already exist
$hasQualifications = Schema::hasColumn('profiles', 'qualifications');
$hasTeachingStyle = Schema::hasColumn('profiles', 'teaching_style');
$hasAvailability = Schema::hasColumn('profiles', 'availability');
$hasHireRate = Schema::hasColumn('profiles', 'hire_rate');
$hasHireCurrency = Schema::hasColumn('profiles', 'hire_currency');
$hasSocialLinks = Schema::hasColumn('profiles', 'social_links');

echo "Checking profiles table structure...\n";
echo "qualifications column exists: " . ($hasQualifications ? "Yes" : "No") . "\n";
echo "teaching_style column exists: " . ($hasTeachingStyle ? "Yes" : "No") . "\n";
echo "availability column exists: " . ($hasAvailability ? "Yes" : "No") . "\n";
echo "hire_rate column exists: " . ($hasHireRate ? "Yes" : "No") . "\n";
echo "hire_currency column exists: " . ($hasHireCurrency ? "Yes" : "No") . "\n";
echo "social_links column exists: " . ($hasSocialLinks ? "Yes" : "No") . "\n";

// Add missing columns to the profiles table if they don't exist
if (\!$hasQualifications || \!$hasTeachingStyle || \!$hasAvailability || 
    \!$hasHireRate || \!$hasHireCurrency || \!$hasSocialLinks) {
    
    echo "\nAdding missing columns to profiles table...\n";
    
    Schema::table('profiles', function (Blueprint $table) use (
        $hasQualifications, $hasTeachingStyle, $hasAvailability,
        $hasHireRate, $hasHireCurrency, $hasSocialLinks
    ) {
        if (\!$hasQualifications) {
            $table->json('qualifications')->nullable();
            echo "Added qualifications column\n";
        }
        
        if (\!$hasTeachingStyle) {
            $table->string('teaching_style')->nullable();
            echo "Added teaching_style column\n";
        }
        
        if (\!$hasAvailability) {
            $table->json('availability')->nullable();
            echo "Added availability column\n";
        }
        
        if (\!$hasHireRate) {
            $table->decimal('hire_rate', 10, 2)->nullable();
            echo "Added hire_rate column\n";
        }
        
        if (\!$hasHireCurrency) {
            $table->string('hire_currency', 3)->nullable();
            echo "Added hire_currency column\n";
        }
        
        if (\!$hasSocialLinks) {
            $table->json('social_links')->nullable();
            echo "Added social_links column\n";
        }
    });
    
    echo "Table structure updated successfully.\n";
} else {
    echo "All required columns already exist.\n";
}

