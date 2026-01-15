<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    /**
     * Get suggested libraries for onboarding
     */
    public function getSuggestedLibraries(Request $request)
    {
        try {
            // Get approved libraries, ordered by popularity or creation date
            $libraries = OpenLibrary::where(function($query) {
                $query->where('is_approved', true)
                      ->orWhere('approval_status', 'approved');
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($library) {
                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'thumbnail_url' => $library->thumbnail_url,
                    'cover_image_url' => $library->cover_image_url,
                    'type' => $library->type
                ];
            });
            
            return response()->json([
                'libraries' => $libraries
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Get suggested libraries failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get suggested libraries: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Follow multiple libraries during onboarding
     */
    public function followLibraries(Request $request)
    {
        $request->validate([
            'library_ids' => 'required|array',
            'library_ids.*' => 'exists:open_libraries,id'
        ]);
        
        try {
            $user = Auth::user();
            $libraryIds = $request->library_ids;
            
            // Get libraries that user is not already following
            $existingFollows = DB::table('library_follows')
                ->where('user_id', $user->id)
                ->whereIn('library_id', $libraryIds)
                ->pluck('library_id')
                ->toArray();
            
            $newFollows = array_diff($libraryIds, $existingFollows);
            
            // Insert new follows
            $insertData = [];
            foreach ($newFollows as $libraryId) {
                $insertData[] = [
                    'user_id' => $user->id,
                    'library_id' => $libraryId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            if (!empty($insertData)) {
                DB::table('library_follows')->insert($insertData);
            }
            
            // Mark onboarding as completed (you might want to add an onboarding_completed field to users table)
            // For now, we'll just return success
            
            return response()->json([
                'message' => 'Libraries followed successfully',
                'followed_count' => count($newFollows),
                'already_following_count' => count($existingFollows)
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Follow libraries failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to follow libraries: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check if user has completed onboarding
     */
    public function checkOnboardingStatus(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if user has followed any libraries
            $hasFollowedLibraries = DB::table('library_follows')
                ->where('user_id', $user->id)
                ->exists();
            
            return response()->json([
                'onboarding_completed' => $hasFollowedLibraries,
                'has_followed_libraries' => $hasFollowedLibraries
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check onboarding status: ' . $e->getMessage()
            ], 500);
        }
    }
}

