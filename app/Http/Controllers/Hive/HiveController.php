<?php

namespace App\Http\Controllers\Hive;

use App\Http\Controllers\Controller;
use App\Models\Hive\Activity;
use App\Models\Hive\Channel;
use App\Models\Hive\Community;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HiveController extends Controller
{
    /**
     * Get user's channels
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChannels()
    {
        $user = Auth::user();
        
        try {
            // Get all channels
            $channels = Channel::where('status', 'active')->get();
            
            // Format channels with joined status
            $formattedChannels = $channels->map(function ($channel) use ($user) {
                $isJoined = $channel->hasUser($user->id);
                
                return [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'description' => $channel->description,
                    'color' => $channel->color,
                    'member_count' => $channel->getMemberCountAttribute(),
                    'is_joined' => $isJoined,
                    'created_at' => $channel->created_at->toIso8601String(),
                    'updated_at' => $channel->updated_at->toIso8601String(),
                ];
            });
            
            return response()->json([
                'message' => 'Channels retrieved successfully',
                'channels' => $formattedChannels
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve channels: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to retrieve channels',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get user's communities
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCommunities()
    {
        $user = Auth::user();
        
        try {
            // Get all communities
            $communities = Community::where('status', 'active')->get();
            
            // Format communities with joined status
            $formattedCommunities = $communities->map(function ($community) use ($user) {
                $isJoined = $community->hasUser($user->id);
                
                return [
                    'id' => $community->id,
                    'name' => $community->name,
                    'description' => $community->description,
                    'avatar' => $community->avatar,
                    'member_count' => $community->member_count,
                    'is_joined' => $isJoined,
                    'privacy' => $community->privacy,
                    'created_at' => $community->created_at->toIso8601String(),
                    'updated_at' => $community->updated_at->toIso8601String(),
                ];
            });
            
            return response()->json([
                'message' => 'Communities retrieved successfully',
                'communities' => $formattedCommunities
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve communities: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to retrieve communities',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get recent activity feed
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivity(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        
        try {
            // Get user's activity
            $activities = Activity::where('target_user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Format activities
            $formattedActivities = $activities->map(function ($activity) {
                return $activity->toActivityResponse();
            });
            
            return response()->json([
                'message' => 'Activity retrieved successfully',
                'activity' => $formattedActivities,
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'per_page' => $activities->perPage(),
                    'total' => $activities->total(),
                    'has_more' => $activities->hasMorePages()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve activity: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to retrieve activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Join a channel
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function joinChannel($id)
    {
        $user = Auth::user();
        
        try {
            $channel = Channel::findOrFail($id);
            
            // Check if already a member
            if ($channel->hasUser($user->id)) {
                return response()->json([
                    'message' => 'User is already a member of this channel',
                    'channel' => [
                        'id' => $channel->id,
                        'name' => $channel->name,
                        'is_joined' => true,
                        'member_count' => $channel->getMemberCountAttribute()
                    ]
                ]);
            }
            
            // Check privacy settings - implement more complex logic if needed
            if ($channel->privacy === 'private') {
                // For private channels, you might require an invite or admin approval
                // This is a simplified version
                return response()->json([
                    'message' => 'This is a private channel and requires an invitation',
                ], 403);
            }
            
            // Add user as member
            $channel->addMember($user->id);
            
            return response()->json([
                'message' => 'Successfully joined channel',
                'channel' => [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'is_joined' => true,
                    'member_count' => $channel->getMemberCountAttribute()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to join channel: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to join channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Leave a channel
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function leaveChannel($id)
    {
        $user = Auth::user();
        
        try {
            $channel = Channel::findOrFail($id);
            
            // Check if user is a member
            if (!$channel->hasUser($user->id)) {
                return response()->json([
                    'message' => 'User is not a member of this channel',
                    'channel' => [
                        'id' => $channel->id,
                        'name' => $channel->name,
                        'is_joined' => false,
                        'member_count' => $channel->getMemberCountAttribute()
                    ]
                ]);
            }
            
            // Remove user from channel
            $channel->removeMember($user->id);
            
            return response()->json([
                'message' => 'Successfully left channel',
                'channel' => [
                    'id' => $channel->id,
                    'name' => $channel->name,
                    'is_joined' => false,
                    'member_count' => $channel->getMemberCountAttribute()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to leave channel: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to leave channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Join a community
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function joinCommunity($id)
    {
        $user = Auth::user();
        
        try {
            $community = Community::findOrFail($id);
            
            // Check if already a member
            if ($community->hasUser($user->id)) {
                return response()->json([
                    'message' => 'User is already a member of this community',
                    'community' => [
                        'id' => $community->id,
                        'name' => $community->name,
                        'is_joined' => true,
                        'member_count' => $community->member_count
                    ]
                ]);
            }
            
            // Check privacy settings
            if ($community->privacy === 'private' || $community->privacy === 'invite-only') {
                // For private communities, implement more complex logic like invite codes
                return response()->json([
                    'message' => 'This is a private community and requires an invitation',
                ], 403);
            }
            
            // Add user as member
            $community->addMember($user->id);
            
            return response()->json([
                'message' => 'Successfully joined community',
                'community' => [
                    'id' => $community->id,
                    'name' => $community->name,
                    'is_joined' => true,
                    'member_count' => $community->member_count
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to join community: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to join community',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Leave a community
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function leaveCommunity($id)
    {
        $user = Auth::user();
        
        try {
            $community = Community::findOrFail($id);
            
            // Check if user is a member
            if (!$community->hasUser($user->id)) {
                return response()->json([
                    'message' => 'User is not a member of this community',
                    'community' => [
                        'id' => $community->id,
                        'name' => $community->name,
                        'is_joined' => false,
                        'member_count' => $community->member_count
                    ]
                ]);
            }
            
            // Remove user from community
            $community->removeMember($user->id);
            
            return response()->json([
                'message' => 'Successfully left community',
                'community' => [
                    'id' => $community->id,
                    'name' => $community->name,
                    'is_joined' => false,
                    'member_count' => $community->member_count
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to leave community: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to leave community',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
