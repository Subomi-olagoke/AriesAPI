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
     * Check if a source string indicates a regular channel
     *
     * @param string $source
     * @return bool
     */
    private function isRegularChannel($source)
    {
        return $source === 'regular';
    }
    
    /**
     * Check if a string is a UUID
     *
     * @param string $str
     * @return bool
     */
    private function isUuid($str) 
    {
        if (!is_string($str)) return false;
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $str) === 1;
    }
    
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
    
    /**
     * Join a channel - handles both regular channels and hive channels
     *
     * @param int|string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function joinChannel(Request $request, $id)
    {
        $user = Auth::user();
        
        // Validate the request for join message
        $validated = $request->validate([
            'join_message' => 'nullable|string|max:1000'
        ]);
        
        $joinMessage = $validated['join_message'] ?? null;
        
        try {
            // Determine if it's likely a regular channel (UUID) or hive channel (numeric ID)
            if ($this->isUuid($id) && Schema::hasTable('channels')) {
                // This is likely a regular channel
                $channel = DB::table('channels')->where('id', $id)->first();
                
                if (!$channel) {
                    return response()->json(['message' => 'Channel not found'], 404);
                }
                
                // Check if already a member
                if (Schema::hasTable('channel_members')) {
                    $existingMembership = DB::table('channel_members')
                        ->where('channel_id', $channel->id)
                        ->where('user_id', $user->id)
                        ->first();
                    
                    if ($existingMembership) {
                        if ($existingMembership->status === 'approved') {
                            return response()->json([
                                'message' => 'You are already a member of this channel',
                                'channel' => [
                                    'id' => $channel->id,
                                    'name' => $channel->title,
                                    'is_joined' => true
                                ]
                            ]);
                        } elseif ($existingMembership->status === 'pending') {
                            return response()->json([
                                'message' => 'Your join request is already pending approval'
                            ], 400);
                        }
                    }
                    
                    // Always create as pending membership for Hive flow
                    DB::table('channel_members')->insert([
                        'channel_id' => $channel->id,
                        'user_id' => $user->id,
                        'role' => 'member',
                        'status' => 'pending',
                        'is_active' => false,
                        'join_message' => $joinMessage,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    // Notify channel admins - find the creator/owner
                    $creator = DB::table('users')->where('id', $channel->creator_id)->first();
                    if ($creator) {
                        // Create a notification record if we have a notifications table
                        if (Schema::hasTable('notifications')) {
                            $notificationData = [
                                'id' => (string) \Illuminate\Support\Str::uuid(),
                                'type' => 'App\\Notifications\\GeneralNotification',
                                'notifiable_type' => 'App\\Models\\User',
                                'notifiable_id' => $creator->id,
                                'data' => json_encode([
                                    'message' => $user->username . ' has requested to join your channel ' . $channel->title,
                                    'type' => 'channel_join_request',
                                    'level' => 'info',
                                    'data' => [
                                        'channel_id' => $channel->id,
                                        'channel_title' => $channel->title,
                                        'user_id' => $user->id,
                                        'username' => $user->username,
                                        'join_message' => $joinMessage
                                    ]
                                ]),
                                'created_at' => now(),
                                'updated_at' => now()
                            ];
                            
                            DB::table('notifications')->insert($notificationData);
                        }
                    }
                    
                    return response()->json([
                        'message' => 'Join request submitted successfully',
                        'channel' => [
                            'id' => $channel->id,
                            'name' => $channel->title,
                            'is_joined' => false,
                            'has_pending_request' => true
                        ]
                    ], 201);
                }
            } else if (Schema::hasTable('hive_channels')) {
                // This is likely a hive channel
                $channel = DB::table('hive_channels')->where('id', $id)->first();
                
                if (!$channel) {
                    return response()->json(['message' => 'Hive channel not found'], 404);
                }
                
                // Check if already a member
                if (Schema::hasTable('hive_channel_members')) {
                    $membership = DB::table('hive_channel_members')
                        ->where('channel_id', $channel->id)
                        ->where('user_id', $user->id)
                        ->first();
                    
                    if ($membership) {
                        return response()->json([
                            'message' => 'User is already a member of this channel',
                            'channel' => [
                                'id' => $channel->id,
                                'name' => $channel->name,
                                'is_joined' => true
                            ]
                        ]);
                    }
                    
                    // For Hive channels, create as pending with join message
                    $membershipData = [
                        'channel_id' => $channel->id,
                        'user_id' => $user->id,
                        'role' => 'member',
                        'notifications_enabled' => true,
                        'status' => 'pending', // Add status column if doesn't exist
                        'join_message' => $joinMessage,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    
                    DB::table('hive_channel_members')->insert($membershipData);
                    
                    // Notify channel creator
                    // Find the creator's user ID - assuming creator_id is stored in the channels table
                    if (Schema::hasTable('notifications')) {
                        $notificationData = [
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'type' => 'App\\Notifications\\GeneralNotification',
                            'notifiable_type' => 'App\\Models\\User',
                            'notifiable_id' => $channel->creator_id,
                            'data' => json_encode([
                                'message' => $user->username . ' has requested to join your hive channel ' . $channel->name,
                                'type' => 'hive_channel_join_request',
                                'level' => 'info',
                                'data' => [
                                    'channel_id' => $channel->id,
                                    'channel_name' => $channel->name,
                                    'user_id' => $user->id,
                                    'username' => $user->username,
                                    'join_message' => $joinMessage
                                ]
                            ]),
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                        
                        DB::table('notifications')->insert($notificationData);
                    }
                    
                    return response()->json([
                        'message' => 'Join request submitted successfully',
                        'channel' => [
                            'id' => $channel->id,
                            'name' => $channel->name,
                            'is_joined' => false,
                            'has_pending_request' => true
                        ]
                    ], 201);
                }
            }
            
            // Fallback if neither table exists
            return response()->json([
                'message' => 'Channel system not properly configured'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to join channel: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to join channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Leave a channel - handles both regular channels and hive channels
     *
     * @param int|string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function leaveChannel($id)
    {
        $user = Auth::user();
        
        try {
            // Determine if it's likely a regular channel (UUID) or hive channel (numeric ID)
            if ($this->isUuid($id) && Schema::hasTable('channels')) {
                // This is likely a regular channel
                $channel = DB::table('channels')->where('id', $id)->first();
                
                if (!$channel) {
                    return response()->json(['message' => 'Channel not found'], 404);
                }
                
                // Check if user is a member
                if (Schema::hasTable('channel_members')) {
                    $membership = DB::table('channel_members')
                        ->where('channel_id', $channel->id)
                        ->where('user_id', $user->id)
                        ->first();
                    
                    if (!$membership) {
                        return response()->json(['message' => 'You are not a member of this channel'], 403);
                    }
                    
                    // Check if user is the creator
                    if ($channel->creator_id === $user->id) {
                        return response()->json(['message' => 'The channel creator cannot leave the channel. Transfer ownership first or delete the channel.'], 400);
                    }
                    
                    // Remove membership
                    DB::table('channel_members')
                        ->where('channel_id', $channel->id)
                        ->where('user_id', $user->id)
                        ->delete();
                    
                    return response()->json([
                        'message' => 'Successfully left the channel'
                    ]);
                }
            } else if (Schema::hasTable('hive_channels')) {
                // This is likely a hive channel
                $channel = DB::table('hive_channels')->where('id', $id)->first();
                
                if (!$channel) {
                    return response()->json(['message' => 'Hive channel not found'], 404);
                }
                
                // Check if user is a member
                if (Schema::hasTable('hive_channel_members')) {
                    $isMember = DB::table('hive_channel_members')
                        ->where('channel_id', $channel->id)
                        ->where('user_id', $user->id)
                        ->exists();
                    
                    if (!$isMember) {
                        return response()->json(['message' => 'User is not a member of this channel'], 403);
                    }
                    
                    // Remove user from channel
                    DB::table('hive_channel_members')
                        ->where('channel_id', $channel->id)
                        ->where('user_id', $user->id)
                        ->delete();
                    
                    return response()->json([
                        'message' => 'Successfully left channel'
                    ]);
                }
            }
            
            // Fallback if neither table exists
            return response()->json([
                'message' => 'Channel system not properly configured'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to leave channel: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to leave channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}