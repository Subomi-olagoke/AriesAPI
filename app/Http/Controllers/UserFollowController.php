<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\AlexPointsService;
use App\Notifications\FollowNotification;

class UserFollowController extends Controller
{
    protected $alexPointsService;
    
    public function __construct(AlexPointsService $alexPointsService)
    {
        $this->alexPointsService = $alexPointsService;
    }
    
    /**
     * Follow a user
     */
    public function follow(Request $request, $userId)
    {
        try {
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            // Can't follow yourself
            if ($currentUser->id === $userId) {
                return response()->json([
                    'message' => 'You cannot follow yourself'
                ], 400);
            }
            
            // Check if user exists
            $userToFollow = User::find($userId);
            if (!$userToFollow) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }
            
            // Check if already following
            $exists = DB::table('follows')
                ->where('user_id', $currentUser->id)
                ->where('followeduser', $userId)
                ->exists();
                
            if ($exists) {
                return response()->json([
                    'message' => 'You are already following this user',
                    'is_following' => true
                ], 200);
            }
            
            // Create follow relationship
            DB::table('follows')->insert([
                'user_id' => $currentUser->id,
                'followeduser' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Send notification to the user being followed
            $userToFollow->notify(new FollowNotification($currentUser));
            
            // Award points for following a user
            try {
                $this->alexPointsService->addPoints(
                    $currentUser,
                    'follow_user',
                    User::class,
                    $userId,
                    "Followed user: {$userToFollow->username}"
                );
            } catch (\Exception $e) {
                Log::warning('Failed to award points for following user: ' . $e->getMessage());
            }
            
            // Award points to the user who gained a follower
            try {
                $this->alexPointsService->addPoints(
                    $userToFollow,
                    'gained_follower',
                    User::class,
                    $currentUser->id,
                    "Gained a new follower: {$currentUser->username}"
                );
            } catch (\Exception $e) {
                Log::warning('Failed to award points for gaining follower: ' . $e->getMessage());
            }
            
            return response()->json([
                'message' => 'Successfully followed user',
                'is_following' => true
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to follow user: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Unfollow a user
     */
    public function unfollow(Request $request, $userId)
    {
        try {
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            // Delete follow relationship
            $deleted = DB::table('follows')
                ->where('user_id', $currentUser->id)
                ->where('followeduser', $userId)
                ->delete();
                
            if ($deleted === 0) {
                return response()->json([
                    'message' => 'You are not following this user',
                    'is_following' => false
                ], 200);
            }
            
            return response()->json([
                'message' => 'Successfully unfollowed user',
                'is_following' => false
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to unfollow user: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get list of users that the current user follows
     */
    public function following(Request $request)
    {
        try {
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            $following = DB::table('follows')
                ->join('users', 'follows.followeduser', '=', 'users.id')
                ->where('follows.user_id', $currentUser->id)
                ->select(
                    'users.id',
                    'users.username',
                    'users.first_name',
                    'users.last_name',
                    'users.avatar',
                    'users.is_verified',
                    'users.role',
                    'follows.created_at as followed_at'
                )
                ->orderBy('follows.created_at', 'desc')
                ->get();
                
            return response()->json([
                'following' => $following,
                'count' => $following->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get following list: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get list of users following the current user
     */
    public function followers(Request $request)
    {
        try {
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            $followers = DB::table('follows')
                ->join('users', 'follows.user_id', '=', 'users.id')
                ->where('follows.followeduser', $currentUser->id)
                ->select(
                    'users.id',
                    'users.username',
                    'users.first_name',
                    'users.last_name',
                    'users.avatar',
                    'users.is_verified',
                    'users.role',
                    'follows.created_at as followed_at'
                )
                ->orderBy('follows.created_at', 'desc')
                ->get();
                
            return response()->json([
                'followers' => $followers,
                'count' => $followers->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get followers list: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check if current user is following a specific user
     */
    public function checkFollowing(Request $request, $userId)
    {
        try {
            $currentUser = Auth::user();
            
            if (!$currentUser) {
                return response()->json([
                    'is_following' => false
                ], 200);
            }
            
            $isFollowing = DB::table('follows')
                ->where('user_id', $currentUser->id)
                ->where('followeduser', $userId)
                ->exists();
                
            return response()->json([
                'is_following' => $isFollowing
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check follow status: ' . $e->getMessage(),
                'is_following' => false
            ], 500);
        }
    }
}
