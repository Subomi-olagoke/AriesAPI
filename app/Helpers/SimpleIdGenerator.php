<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SimpleIdGenerator
{
    /**
     * Get the next sequential ID for a table
     * This ensures the ID starts from 1 and increments properly
     *
     * @param string $tableName The name of the table to get the next ID for
     * @return int The next available ID
     */
    public static function getNextId(string $tableName): int
{
    // Check if the table exists
    if (!Schema::hasTable($tableName)) {
        return 1; // Start with 1 if the table doesn't exist
    }
    
    // Get the maximum ID from the table and ensure it's an integer
    $maxId = (int)(DB::table($tableName)->max('id') ?? 0);
    
    // Return the next ID (starting from 1 if no records exist)
    return $maxId + 1;
}
}