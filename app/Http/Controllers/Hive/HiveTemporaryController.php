<?php

namespace App\Http\Controllers\Hive;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HiveTemporaryController extends Controller
{
    /**
     * Get user's channels including both hive_channels and regular channels
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChannels()
    {
        $user = Auth::user();
        $allChannels = collect([]);
        
        try {
            // Get all hive channels if the table exists
            if (Schema::hasTable('hive_channels')) {
                $hiveChannels = DB::table('hive_channels')
                    ->where('status', 'active')
                    ->get()
                    ->map(function ($channel) use ($user) {
                        $isJoined = false;
                        
                        // Check if user is a member if the members table exists
                        if (Schema::hasTable('hive_channel_members')) {
                            $isJoined = DB::table('hive_channel_members')
                                ->where('channel_id', $channel->id)
                                ->where('user_id', $user->id)
                                ->exists();
                        }
                        
                        return [
                            'id' => $channel->id,
                            'name' => $channel->name,
                            'description' => $channel->description,
                            'color' => $channel->color,
                            'member_count' => 0,
                            'is_joined' => $isJoined,
                            'created_at' => $channel->created_at,
                            'updated_at' => $channel->updated_at,
                            'source' => 'hive'
                        ];
                    });
                
                $allChannels = $allChannels->concat($hiveChannels);
            }
            
            // Add regular channels to the mix
            if (Schema::hasTable('channels')) {
                // Get user's channels
                $regularChannels = DB::table('channels')
                    ->where('is_active', true)
                    ->get()
                    ->map(function ($channel) use ($user) {
                        $isJoined = false;
                        $memberCount = 0;
                        
                        // Check if user is a member and get member count
                        if (Schema::hasTable('channel_members')) {
                            $isJoined = DB::table('channel_members')
                                ->where('channel_id', $channel->id)
                                ->where('user_id', $user->id)
                                ->where('status', 'approved')
                                ->exists();
                                
                            $memberCount = DB::table('channel_members')
                                ->where('channel_id', $channel->id)
                                ->where('status', 'approved')
                                ->count();
                        }
                        
                        return [
                            'id' => $channel->id,
                            'name' => $channel->title, // Regular channels use 'title' not 'name'
                            'description' => $channel->description,
                            'color' => '#007AFF', // Default color
                            'member_count' => $memberCount,
                            'is_joined' => $isJoined,
                            'created_at' => $channel->created_at,
                            'updated_at' => $channel->updated_at,
                            'source' => 'regular',
                            'picture' => $channel->picture
                        ];
                    });
                
                $allChannels = $allChannels->concat($regularChannels);
            }
            
            // Sort by creation date
            $allChannels = $allChannels->sortByDesc('created_at')->values();
            
            return response()->json([
                'message' => 'Channels retrieved successfully',
                'channels' => $allChannels
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve channels: ' . $e->getMessage());
            
            // Graceful fallback
            return response()->json([
                'message' => 'Error retrieving channels: ' . $e->getMessage(),
                'channels' => []
            ]);
        }
    }
    
    /**
     * Get user's communities with error handling for missing tables
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCommunities()
    {
        if (!Schema::hasTable('hive_communities')) {
            return response()->json([
                'message' => 'Hive feature is coming soon',
                'communities' => []
            ]);
        }
        
        $user = Auth::user();
        
        try {
            // Return empty list for now
            return response()->json([
                'message' => 'Communities retrieved successfully',
                'communities' => []
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve communities: ' . $e->getMessage());
            
            // Graceful fallback
            return response()->json([
                'message' => 'Hive feature is coming soon',
                'communities' => []
            ]);
        }
    }
    
    /**
     * Get recent activity feed with error handling for missing tables
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivity(Request $request)
    {
        if (!Schema::hasTable('hive_activities')) {
            return response()->json([
                'message' => 'Hive feature is coming soon',
                'activity' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                    'has_more' => false
                ]
            ]);
        }
        
        $user = Auth::user();
        
        try {
            // Return empty list for now
            return response()->json([
                'message' => 'Activity retrieved successfully',
                'activity' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                    'has_more' => false
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve activity: ' . $e->getMessage());
            
            // Graceful fallback
            return response()->json([
                'message' => 'Hive feature is coming soon',
                'activity' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                    'has_more' => false
                ]
            ]);
        }
    }
}