<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\ChannelMessage;
use App\Events\ChannelMessageSent;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChannelController extends Controller
{
    /**
     * Get all channels the authenticated user is a member of
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        $channels = $user->channels()
            ->with(['latestMessage.sender', 'creator'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($channel) use ($user) {
                // Get the user's membership
                $membership = $channel->members()->where('user_id', $user->id)->first();
                
                // Add unread count
                $channel->unread_count = $channel->unreadMessagesCount($user);
                
                // Add user's role in the channel
                $channel->user_role = $membership ? $membership->role : null;
                
                return $channel;
            });
        
        return response()->json([
            'channels' => $channels
        ]);
    }
    
    /**
     * Create a new channel
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'max_members' => 'integer|min:2|max:20'
        ]);
        
        // Check if user has permission to create channels
        if (!$user->canCreateChannels() && !$user->isAdmin) {
            return response()->json([
                'message' => 'You need an active subscription to create channels'
            ], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Create channel
            $channel = Channel::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'creator_id' => $user->id,
                'max_members' => $validated['max_members'] ?? 10,
                'share_link' => 'channel/' . Str::random(12)
            ]);
            
            // Add creator as admin member
            $member = ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'is_active' => true,
                'joined_at' => now(),
                'last_read_at' => now()
            ]);
            
            DB::commit();
            
            // Load relations for response
            $channel->load('creator');
            $channel->user_role = 'admin';
            $channel->unread_count = 0;
            
            return response()->json([
                'message' => 'Channel created successfully',
                'channel' => $channel
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create channel: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to create channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get a specific channel with messages
     */
    public function show($id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel with relationships
        $channel = Channel::with(['messages.sender', 'members.user', 'creator'])
            ->findOrFail($id);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get the user's membership
        $membership = $channel->members()->where('user_id', $user->id)->first();
        
        // Add user's role in the channel
        $channel->user_role = $membership ? $membership->role : null;
        
        // Mark messages as read
        if ($membership) {
            $membership->markAsRead();
        }
        
        return response()->json([
            'channel' => $channel
        ]);
    }
    
    /**
     * Update channel details
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is an admin
        if (!$channel->isAdmin($user)) {
            return response()->json(['message' => 'Only channel admins can update channel details'], 403);
        }
        
        // Validate request
        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string|max:1000',
            'max_members' => 'integer|min:2|max:20'
        ]);
        
        try {
            // Update channel
            $channel->update($validated);
            
            return response()->json([
                'message' => 'Channel updated successfully',
                'channel' => $channel
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update channel: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a channel
     */
    public function destroy($id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is the creator or an admin
        if ($channel->creator_id !== $user->id && !$user->isAdmin) {
            return response()->json(['message' => 'Only the channel creator can delete the channel'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Delete all messages and members
            $channel->messages()->delete();
            $channel->members()->delete();
            
            // Delete channel
            $channel->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Channel deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete channel: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to delete channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Send a message to the channel
     */
    public function sendMessage(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'message' => 'required_without:attachment|string|max:10000',
            'attachment' => 'nullable|file|max:10240'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Create message data
            $messageData = [
                'channel_id' => $channel->id,
                'sender_id' => $user->id,
                'body' => $request->message ?? '',
                'read_by' => [$user->id]
            ];
            
            // Handle attachment if present
            if ($request->hasFile('attachment')) {
                $fileUploadService = app(FileUploadService::class);
                
                $attachmentUrl = $fileUploadService->uploadFile(
                    $request->file('attachment'),
                    'channel_attachments'
                );
                
                $messageData['attachment'] = $attachmentUrl;
                $messageData['attachment_type'] = $request->file('attachment')->getMimeType();
            }
            
            // Create message
            $message = ChannelMessage::create($messageData);
            
            // Process mentions in the message body
            if (!empty($messageData['body'])) {
                $message->processMentions($messageData['body']);
            }
            
            // Update channel's updated_at timestamp
            $channel->touch();
            
            // Update sender's last read timestamp
            $membership = $channel->members()->where('user_id', $user->id)->first();
            if ($membership) {
                $membership->markAsRead();
            }
            
            DB::commit();
            
            // Load relationships for response
            $message->load('sender');
            
            // Broadcast event
            broadcast(new ChannelMessageSent($message));
            
            return response()->json([
                'message' => $message
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to send message: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add a member to the channel
     */
    public function addMember(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|in:admin,member'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is an admin of the channel
        if (!$channel->isAdmin($user)) {
            return response()->json(['message' => 'Only channel admins can add members'], 403);
        }
        
        // Check if channel has reached maximum members
        if ($channel->hasReachedMaxMembers()) {
            return response()->json(['message' => 'Channel has reached maximum number of members'], 400);
        }
        
        // Get the user to add
        $newMember = User::findOrFail($validated['user_id']);
        
        // Check if user is already a member
        if ($channel->isMember($newMember)) {
            return response()->json(['message' => 'User is already a member of this channel'], 400);
        }
        
        try {
            // Add member
            $membership = ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id' => $newMember->id,
                'role' => $validated['role'] ?? 'member',
                'is_active' => true,
                'joined_at' => now()
            ]);
            
            // Load user relationship
            $membership->load('user');
            
            return response()->json([
                'message' => 'Member added successfully',
                'member' => $membership
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to add member: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to add member',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove a member from the channel
     */
    public function removeMember(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is an admin of the channel or removing themselves
        if (!$channel->isAdmin($user) && $user->id !== $validated['user_id']) {
            return response()->json(['message' => 'Only channel admins can remove other members'], 403);
        }
        
        // Check if trying to remove the creator
        if ($channel->creator_id === $validated['user_id']) {
            return response()->json(['message' => 'Cannot remove the channel creator'], 400);
        }
        
        // Get the membership to remove
        $membership = $channel->members()->where('user_id', $validated['user_id'])->first();
        
        if (!$membership) {
            return response()->json(['message' => 'User is not a member of this channel'], 404);
        }
        
        try {
            // Delete membership
            $membership->delete();
            
            return response()->json([
                'message' => 'Member removed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove member: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to remove member',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update a member's role in the channel
     */
    public function updateMemberRole(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,member'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is an admin of the channel
        if (!$channel->isAdmin($user)) {
            return response()->json(['message' => 'Only channel admins can update member roles'], 403);
        }
        
        // Get the membership to update
        $membership = $channel->members()->where('user_id', $validated['user_id'])->first();
        
        if (!$membership) {
            return response()->json(['message' => 'User is not a member of this channel'], 404);
        }
        
        try {
            // Update role
            $membership->update(['role' => $validated['role']]);
            
            // Load user relationship
            $membership->load('user');
            
            return response()->json([
                'message' => 'Member role updated successfully',
                'member' => $membership
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update member role: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update member role',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Join a channel using a share link
     */
    public function joinWithLink(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'share_link' => 'required|string'
        ]);
        
        // Find channel by share link
        $channel = Channel::where('share_link', $validated['share_link'])->first();
        
        if (!$channel) {
            return response()->json(['message' => 'Invalid share link'], 404);
        }
        
        // Check if channel is active
        if (!$channel->is_active) {
            return response()->json(['message' => 'This channel is no longer active'], 400);
        }
        
        // Check if user is already a member
        if ($channel->isMember($user)) {
            return response()->json(['message' => 'You are already a member of this channel'], 400);
        }
        
        // Check if channel has reached maximum members
        if ($channel->hasReachedMaxMembers()) {
            return response()->json(['message' => 'Channel has reached maximum number of members'], 400);
        }
        
        try {
            // Add member
            $membership = ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'role' => 'member',
                'is_active' => true,
                'joined_at' => now()
            ]);
            
            // Load relationships for response
            $channel->load(['messages.sender', 'members.user', 'creator']);
            $channel->user_role = 'member';
            $channel->unread_count = $channel->unreadMessagesCount($user);
            
            return response()->json([
                'message' => 'Successfully joined the channel',
                'channel' => $channel
            ], 201);
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
     */
    public function leave($id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Check if user is the creator
        if ($channel->creator_id === $user->id) {
            return response()->json(['message' => 'The channel creator cannot leave the channel. Transfer ownership first or delete the channel.'], 400);
        }
        
        try {
            // Remove membership
            $channel->members()->where('user_id', $user->id)->delete();
            
            return response()->json([
                'message' => 'Successfully left the channel'
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
     * Mark all messages in a channel as read
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        try {
            // Get the membership
            $membership = $channel->members()->where('user_id', $user->id)->first();
            
            if ($membership) {
                $membership->markAsRead();
            }
            
            return response()->json([
                'message' => 'Messages marked as read'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark messages as read: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get the share link for a channel
     */
    public function getShareLink($id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        return response()->json([
            'share_link' => $channel->share_link
        ]);
    }
    
    /**
     * Regenerate the share link for a channel
     */
    public function regenerateShareLink($id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is an admin
        if (!$channel->isAdmin($user)) {
            return response()->json(['message' => 'Only channel admins can regenerate the share link'], 403);
        }
        
        try {
            // Generate new share link
            $channel->share_link = 'channel/' . Str::random(12);
            $channel->save();
            
            return response()->json([
                'message' => 'Share link regenerated',
                'share_link' => $channel->share_link
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to regenerate share link: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to regenerate share link',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
