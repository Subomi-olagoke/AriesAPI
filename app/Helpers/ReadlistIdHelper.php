<?php

namespace App\Helpers;

use App\Models\Readlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReadlistIdHelper
{
    /**
     * Get the next sequential ID for a readlist
     * This ensures the ID starts from 1 and increments properly
     *
     * @return int The next available ID
     */
    public static function getNextId(): int
    {
        try {
            // Begin a transaction to ensure we get a consistent ID
            DB::beginTransaction();
            
            // Get the maximum ID from the readlist table
            $maxId = Readlist::max('id') ?? 0;
            
            // Calculate the next ID (starting from 1 if no records exist)
            $nextId = $maxId + 1;
            
            // Create a placeholder record to reserve the ID
            // This will be overwritten by the actual record later
            $placeholder = new Readlist();
            $placeholder->id = $nextId;
            $placeholder->title = 'Placeholder'; // Temporary value to satisfy required fields
            $placeholder->user_id = auth()->id() ?? 1; // Fallback to user 1 if not authenticated
            $placeholder->save();
            
            // Commit the transaction
            DB::commit();
            
            return $nextId;
        } catch (\Exception $e) {
            // Roll back the transaction if anything goes wrong
            DB::rollBack();
            
            // Log the error
            Log::error('Failed to generate next readlist ID', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback: Just get the max ID and increment
            $maxId = Readlist::max('id') ?? 0;
            return $maxId + 1;
        }
    }
    
    /**
     * Update the placeholder readlist with actual data
     *
     * @param int $id The readlist ID
     * @param array $data The actual readlist data
     * @return bool Whether the update was successful
     */
    public static function updateReadlist(int $id, array $data): bool
    {
        try {
            $readlist = Readlist::findOrFail($id);
            return $readlist->update($data);
        } catch (\Exception $e) {
            Log::error('Failed to update placeholder readlist', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}