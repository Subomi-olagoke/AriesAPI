<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Follow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Notifications\followedNotification;

class FollowController extends Controller {

    /**
     * Follow a user
     * 
     * @param Request $request
     * @param string $username Username of the user to follow
     * @return \Illuminate\Http\JsonResponse
     */
    public function createFollow(Request $request, $username) {
        try {
            // Get the authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    "message" => "Authentication required"
                ], 401);
            }

            // Prevent self-following by comparing usernames
            if ($user->username === $username) {
                return response()->json([
                    "message" => "You cannot follow yourself"
                ], 403);
            }

            // Find the target user by username
            $user2 = User::where('username', $username)->first();
            if (!$user2) {
                return response()->json([
                    'message' => 'User not found',
                    'username' => $username
                ], 404);
            }

            // Check if the authenticated user is already following the target user
            $existCheck = $this->followStat($user2->id);
            if ($existCheck) {
                return response()->json([
                    "message" => "You are already following this user"
                ], 409);
            }

            // Create the follow record using the target user's id
            $newFollow = new Follow();
            $newFollow->user_id = $user->id;
            $newFollow->followeduser = $user2->id;
            $save = $newFollow->save();

            if ($save) {
                // Send notification to followed user
                try {
                    $user2->notify(new followedNotification($user, $user2));
                } catch (\Exception $e) {
                    Log::error('Failed to send follow notification: ' . $e->getMessage());
                    // Continue execution - notification failure shouldn't prevent follow action
                }
                
                return response()->json([
                    'message' => 'Followed successfully',
                    'followed_user' => [
                        'id' => $user2->id,
                        'username' => $user2->username
                    ]
                ], 201);
            } else {
                return response()->json([
                    'message' => 'Failed to create follow relationship'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Follow error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while processing your request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unfollow a user
     * 
     * @param Request $request
     * @param string $username Username of the user to unfollow
     * @return \Illuminate\Http\JsonResponse
     */
    public function unFollow(Request $request, $username) {
        try {
            // Get the authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    "message" => "Authentication required"
                ], 401);
            }

            // Prevent self-unfollowing (or a no-op) by comparing usernames
            if ($user->username === $username) {
                return response()->json([
                    "message" => "You cannot unfollow yourself"
                ], 403);
            }

            // Find the target user by username
            $user2 = User::where('username', $username)->first();
            if (!$user2) {
                return response()->json([
                    'message' => 'User not found',
                    'username' => $username
                ], 404);
            }

            // Check if the follow relationship exists
            $existCheck = $this->followStat($user2->id);
            if (!$existCheck) {
                return response()->json([
                    "message" => "You are not following this user"
                ], 400);
            }

            // Delete the follow record
            $deleted = Follow::where('user_id', $user->id)
                ->where('followeduser', $user2->id)
                ->delete();

            if ($deleted >= 1) {
                return response()->json([
                    "message" => "Unfollowed user successfully",
                    "unfollowed_user" => [
                        "id" => $user2->id,
                        "username" => $user2->username
                    ]
                ], 200);
            }
            
            return response()->json([
                "message" => "Error. Try again later"
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unfollow error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while processing your request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if the authenticated user is following a specific user
     * 
     * @param int $targetUserId ID of the target user
     * @return bool Whether the authenticated user is following the target user
     */
    public function followStat($targetUserId) {
        if (!auth()->check()) {
            return false;
        }
        
        return Follow::where([
            ['user_id', '=', auth()->user()->id],
            ['followeduser', '=', $targetUserId]
        ])->exists();
    }

    /**
     * Check if the authenticated user is following a user by username
     * 
     * @param Request $request
     * @param string $username Username to check
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkFollowStatus(Request $request, $username) {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    "message" => "Authentication required"
                ], 401);
            }
            
            $targetUser = User::where('username', $username)->first();
            
            if (!$targetUser) {
                return response()->json([
                    'message' => 'User not found',
                    'username' => $username
                ], 404);
            }
            
            $isFollowing = $this->followStat($targetUser->id);
            
            return response()->json([
                'is_following' => $isFollowing,
                'user' => [
                    'id' => $targetUser->id,
                    'username' => $targetUser->username
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Follow status check error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while checking follow status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the follower count for a user
     * 
     * @param User $user User to get follower count for
     * @return int Follower count
     */
    public function followerCount(User $user) {
        return $user->followers()->count();
    }

    /**
     * Get the following count for a user
     * 
     * @param User $user User to get following count for
     * @return int Following count
     */
    public function followingCount(User $user) {
        return $user->following()->count();
    }
    
    /**
     * Get users following a specific user
     * 
     * @param Request $request
     * @param string $username Username to get followers for
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFollowers(Request $request, $username) {
        try {
            $user = User::where('username', $username)->first();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                    'username' => $username
                ], 404);
            }
            
            $followers = $user->followers()
                ->with('userDoingTheFollowing:id,username,first_name,last_name,avatar')
                ->paginate(20);
                
            return response()->json([
                'followers' => $followers
            ]);
        } catch (\Exception $e) {
            Log::error('Get followers error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while retrieving followers',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get users that a specific user is following
     * 
     * @param Request $request
     * @param string $username Username to get following for
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFollowing(Request $request, $username) {
        try {
            $user = User::where('username', $username)->first();
            
            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                    'username' => $username
                ], 404);
            }
            
            $following = $user->following()
                ->with('userBeingFollowed:id,username,first_name,last_name,avatar')
                ->paginate(20);
                
            return response()->json([
                'following' => $following
            ]);
        } catch (\Exception $e) {
            Log::error('Get following error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while retrieving following users',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}