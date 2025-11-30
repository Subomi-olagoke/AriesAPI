<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LibraryFollowController extends Controller
{
    /**
     * Follow a library
     */
    public function followLibrary(Request $request, $libraryId)
    {
        try {
            $user = Auth::user();
            $library = OpenLibrary::findOrFail($libraryId);
            
            // Check if already following (assuming library_follows table exists)
            $existing = DB::table('library_follows')
                ->where('user_id', $user->id)
                ->where('library_id', $library->id)
                ->first();
            
            if ($existing) {
                return response()->json([
                    'message' => 'You are already following this library'
                ], 409);
            }
            
            // Follow the library
            DB::table('library_follows')->insert([
                'user_id' => $user->id,
                'library_id' => $library->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return response()->json([
                'message' => 'Library followed successfully',
                'library' => [
                    'id' => $library->id,
                    'name' => $library->name
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Follow library failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to follow library: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Unfollow a library
     */
    public function unfollowLibrary(Request $request, $libraryId)
    {
        try {
            $user = Auth::user();
            $library = OpenLibrary::findOrFail($libraryId);
            
            $deleted = DB::table('library_follows')
                ->where('user_id', $user->id)
                ->where('library_id', $library->id)
                ->delete();
            
            if ($deleted) {
                return response()->json([
                    'message' => 'Library unfollowed successfully'
                ], 200);
            }
            
            return response()->json([
                'message' => 'You are not following this library'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Unfollow library failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to unfollow library: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check if user is following a library
     */
    public function checkFollowStatus(Request $request, $libraryId)
    {
        try {
            $user = Auth::user();
            
            $isFollowing = DB::table('library_follows')
                ->where('user_id', $user->id)
                ->where('library_id', $libraryId)
                ->exists();
            
            return response()->json([
                'is_following' => $isFollowing
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check follow status: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get libraries user is following
     */
    public function getFollowedLibraries(Request $request)
    {
        try {
            $user = Auth::user();
            
            $libraryIds = DB::table('library_follows')
                ->where('user_id', $user->id)
                ->pluck('library_id');
            
            $libraries = OpenLibrary::whereIn('id', $libraryIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($library) {
                    return [
                        'id' => $library->id,
                        'name' => $library->name,
                        'description' => $library->description,
                        'thumbnail_url' => $library->thumbnail_url,
                        'cover_image_url' => $library->cover_image_url,
                        'type' => $library->type,
                        'created_at' => $library->created_at
                    ];
                });
            
            return response()->json([
                'libraries' => $libraries
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Get followed libraries failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get followed libraries: ' . $e->getMessage()
            ], 500);
        }
    }
}

