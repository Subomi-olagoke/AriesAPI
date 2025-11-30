<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FollowOptionsController extends Controller
{
    /**
     * Get suggested users to follow
     * Returns educators and popular users that the current user is not following
     */
    public function getFollowOptions(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Get users that the current user is not following
            // The follows table uses 'followeduser' column name
            $followingIds = DB::table('follows')
                ->where('user_id', $user->id)
                ->pluck('followeduser')
                ->toArray();
            
            // Add current user ID to exclude list
            $excludeIds = array_merge($followingIds, [$user->id]);
            
            // Get suggested users (educators first, then other users)
            // Limit to 20 users
            $suggestedUsers = User::whereNotIn('id', $excludeIds)
                ->where(function($query) {
                    $query->where('role', 'educator')
                          ->orWhere('role', 'learner')
                          ->orWhere('role', 'explorer');
                })
                ->with('profile')
                ->orderByRaw("CASE WHEN role = 'educator' THEN 1 ELSE 2 END")
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function($suggestedUser) {
                    $profile = $suggestedUser->profile;
                    
                    // Get user topics if available
                    $topics = [];
                    try {
                        $topics = $suggestedUser->topic->pluck('name')->toArray();
                    } catch (\Exception $e) {
                        // Topics not available or relationship issue
                        $topics = [];
                    }
                    
                    return [
                        'id' => $suggestedUser->id,
                        'username' => $suggestedUser->username,
                        'bio' => $profile ? ($profile->bio ?? '') : '',
                        'profile_image' => $suggestedUser->getAvatarUrl() ?? '',
                        'topics' => $topics
                    ];
                });
            
            return response()->json([
                'users' => $suggestedUsers
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Get follow options failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get follow options: ' . $e->getMessage(),
                'users' => []
            ], 500);
        }
    }
}

