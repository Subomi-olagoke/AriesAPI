<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\ChannelMessage;
use App\Models\HireRequest;
use App\Events\ChannelMessageSent;
use App\Services\FileUploadService;
use App\Services\ContentModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use App\Notifications\BaseNotification;

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
            ->filter(function ($channel) {
                // Only include channels where the user is an approved member
                return $channel->pivot->status === 'approved';
            })
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
     * Get pending channel requests for the user
     */
    public function pendingRequests()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channels where the user has pending join requests
        $pendingRequests = $user->channelMemberships()
            ->with('channel.creator')
            ->where('status', 'pending')
            ->get();
        
        return response()->json([
            'pending_requests' => $pendingRequests
        ]);
    }
    
    /**
     * Get pending membership requests for channels the user is an admin of
     */
    public function pendingMemberRequests()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channels where the user is an admin
        $adminChannels = $user->channels()
            ->where('channel_members.role', 'admin')
            ->where('channel_members.status', 'approved')
            ->pluck('channels.id');
        
        // Get pending member requests for those channels
        $pendingMembers = ChannelMember::whereIn('channel_id', $adminChannels)
            ->where('status', 'pending')
            ->with(['user', 'channel'])
            ->get();
        
        return response()->json([
            'pending_members' => $pendingMembers
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
            'max_members' => 'integer|min:2|max:20',
            'requires_approval' => 'boolean'
        ]);
        
        // All users can create channels now
        // Previous check removed:
        // if (!$user->canCreateChannels() && !$user->isAdmin) {
        //     return response()->json([
        //         'message' => 'You need an active subscription to create channels'
        //     ], 403);
        // }
        
        try {
            DB::beginTransaction();
            
            // Create channel
            $channel = Channel::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'creator_id' => $user->id,
                'max_members' => $validated['max_members'] ?? 10,
                'requires_approval' => $validated['requires_approval'] ?? false
            ]);
            
            // Add creator as admin member
            $member = ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'status' => 'approved',
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
        
        // Check if user is a member or has a pending request
        $membership = $channel->members()->where('user_id', $user->id)->first();
        
        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // If user is not an approved member, they can only see basic channel info
        if ($membership->status !== 'approved') {
            $channel->setRelation('messages', collect([]));
            $channel->setRelation('members', collect([$membership]));
            $channel->request_status = $membership->status;
            
            return response()->json([
                'channel' => $channel
            ]);
        }
        
        // Add user's role in the channel
        $channel->user_role = $membership->role;
        
        // Mark messages as read
        if ($membership) {
            $membership->markAsRead();
        }
        
        // Check if channel has educators (for learners who might want to hire)
        if ($user->role === User::ROLE_LEARNER) {
            $channel->has_educators = $channel->hasEducators();
            $channel->educators = $channel->educators()->get();
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
            'max_members' => 'integer|min:2|max:20',
            'requires_approval' => 'boolean'
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
        
        // Moderate content before processing
        $contentModerationService = app(ContentModerationService::class);
        $moderationResult = $contentModerationService->moderateMessage(
            $request->message,
            $request->hasFile('attachment') ? $request->file('attachment') : null
        );
        
        if (!$moderationResult['isAllowed']) {
            return response()->json([
                'message' => $moderationResult['reason'] ?? 'Your message contains inappropriate content that is not allowed'
            ], 422);
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
     * Request to join a channel
     */
    public function requestToJoin(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'join_message' => 'nullable|string|max:1000'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is already a member or has a pending request
        $existingMembership = $channel->members()->where('user_id', $user->id)->first();
        
        if ($existingMembership) {
            if ($existingMembership->status === 'approved') {
                return response()->json(['message' => 'You are already a member of this channel'], 400);
            } elseif ($existingMembership->status === 'pending') {
                return response()->json(['message' => 'Your join request is already pending approval'], 400);
            } elseif ($existingMembership->status === 'rejected') {
                // If previously rejected, update the request
                $existingMembership->status = 'pending';
                $existingMembership->join_message = $validated['join_message'] ?? null;
                $existingMembership->rejection_reason = null;
                $existingMembership->save();
                
                // Notify channel admins
                $this->notifyChannelAdmins($channel, $user, 'join_request');
                
                return response()->json([
                    'message' => 'Join request submitted successfully',
                    'membership' => $existingMembership
                ], 200);
            }
        }
        
        // Check if channel has reached maximum members
        if ($channel->hasReachedMaxMembers()) {
            return response()->json(['message' => 'Channel has reached maximum number of members'], 400);
        }
        
        try {
            // If channel requires approval, create pending membership
            if ($channel->requires_approval) {
                $membership = ChannelMember::create([
                    'channel_id' => $channel->id,
                    'user_id' => $user->id,
                    'role' => 'member',
                    'status' => 'pending',
                    'join_message' => $validated['join_message'] ?? null,
                    'is_active' => false
                ]);
                
                // Notify channel admins
                $this->notifyChannelAdmins($channel, $user, 'join_request');
                
                return response()->json([
                    'message' => 'Join request submitted successfully',
                    'membership' => $membership
                ], 201);
            } else {
                // If channel doesn't require approval, add as member immediately
                $membership = ChannelMember::create([
                    'channel_id' => $channel->id,
                    'user_id' => $user->id,
                    'role' => 'member',
                    'status' => 'approved',
                    'is_active' => true,
                    'joined_at' => now()
                ]);
                
                // Load relations for response
                $channel->load(['messages.sender', 'members.user', 'creator']);
                $channel->user_role = 'member';
                
                return response()->json([
                    'message' => 'Successfully joined the channel',
                    'channel' => $channel
                ], 201);
            }
        } catch (\Exception $e) {
            Log::error('Failed to join channel: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to join channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Approve a member's join request
     */
    public function approveMember(Request $request, $id, $memberId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is an admin of the channel
        if (!$channel->isAdmin($user)) {
            return response()->json(['message' => 'Only channel admins can approve join requests'], 403);
        }
        
        // Get the member
        $membership = ChannelMember::where('id', $memberId)
            ->where('channel_id', $channel->id)
            ->where('status', 'pending')
            ->firstOrFail();
        
        try {
            // Approve the member
            $membership->approve();
            
            // Load user relationship
            $membership->load('user');
            
            // Notify the user
            $memberUser = $membership->user;
            $memberUser->notify(new BaseNotification(
                'Your request to join channel ' . $channel->title . ' has been approved',
                'channel_join_approved',
                'success',
                [
                    'channel_id' => $channel->id,
                    'channel_title' => $channel->title
                ]
            ));
            
            return response()->json([
                'message' => 'Member approved successfully',
                'member' => $membership
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to approve member: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to approve member',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reject a member's join request
     */
    public function rejectMember(Request $request, $id, $memberId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is an admin of the channel
        if (!$channel->isAdmin($user)) {
            return response()->json(['message' => 'Only channel admins can reject join requests'], 403);
        }
        
        // Get the member
        $membership = ChannelMember::where('id', $memberId)
            ->where('channel_id', $channel->id)
            ->where('status', 'pending')
            ->firstOrFail();
        
        try {
            // Reject the member
            $membership->reject($validated['rejection_reason'] ?? null);
            
            // Load user relationship
            $membership->load('user');
            
            // Notify the user
            $memberUser = $membership->user;
            $memberUser->notify(new BaseNotification(
                'Your request to join channel ' . $channel->title . ' has been rejected',
                'channel_join_rejected',
                'error',
                [
                    'channel_id' => $channel->id,
                    'channel_title' => $channel->title,
                    'rejection_reason' => $validated['rejection_reason'] ?? 'No reason provided'
                ]
            ));
            
            return response()->json([
                'message' => 'Member rejected successfully',
                'member' => $membership
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reject member: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to reject member',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add a member to the channel (admin only)
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
        $existingMembership = $channel->members()
            ->where('user_id', $newMember->id)
            ->first();
            
        if ($existingMembership) {
            if ($existingMembership->status === 'approved') {
                return response()->json(['message' => 'User is already a member of this channel'], 400);
            } else {
                // Update existing membership to approved
                $existingMembership->status = 'approved';
                $existingMembership->role = $validated['role'] ?? 'member';
                $existingMembership->is_active = true;
                $existingMembership->joined_at = now();
                $existingMembership->save();
                
                // Load user relationship
                $existingMembership->load('user');
                
                return response()->json([
                    'message' => 'Member added successfully',
                    'member' => $existingMembership
                ], 200);
            }
        }
        
        try {
            // Add member
            $membership = ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id' => $newMember->id,
                'role' => $validated['role'] ?? 'member',
                'status' => 'approved',
                'is_active' => true,
                'joined_at' => now()
            ]);
            
            // Load user relationship
            $membership->load('user');
            
            // Notify the user
            $newMember->notify(new BaseNotification(
                'You have been added to channel ' . $channel->title,
                'channel_added',
                'info',
                [
                    'channel_id' => $channel->id,
                    'channel_title' => $channel->title,
                    'added_by' => $user->username
                ]
            ));
            
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
     * Join a channel using a join code
     */
    public function joinWithCode(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'join_code' => 'required|string',
            'join_message' => 'nullable|string|max:1000'
        ]);
        
        // Find channel by join code
        $channel = Channel::where('join_code', $validated['join_code'])->first();
        
        if (!$channel) {
            return response()->json(['message' => 'Invalid join code'], 404);
        }
        
        // Check if channel is active
        if (!$channel->is_active) {
            return response()->json(['message' => 'This channel is no longer active'], 400);
        }
        
        // Check if user is already a member
        $existingMembership = $channel->members()->where('user_id', $user->id)->first();
        
        if ($existingMembership) {
            if ($existingMembership->status === 'approved') {
                return response()->json(['message' => 'You are already a member of this channel'], 400);
            } elseif ($existingMembership->status === 'pending') {
                return response()->json(['message' => 'Your join request is already pending approval'], 400);
            } elseif ($existingMembership->status === 'rejected') {
                // If previously rejected, update the request
                $existingMembership->status = 'pending';
                $existingMembership->join_message = $validated['join_message'] ?? null;
                $existingMembership->rejection_reason = null;
                $existingMembership->save();
                
                // Notify channel admins
                $this->notifyChannelAdmins($channel, $user, 'join_request');
                
                return response()->json([
                    'message' => 'Join request submitted successfully',
                    'membership' => $existingMembership
                ], 200);
            }
        }
        
        // Check if channel has reached maximum members
        if ($channel->hasReachedMaxMembers()) {
            return response()->json(['message' => 'Channel has reached maximum number of members'], 400);
        }
        
        try {
            // If channel requires approval, create pending membership
            if ($channel->requires_approval) {
                $membership = ChannelMember::create([
                    'channel_id' => $channel->id,
                    'user_id' => $user->id,
                    'role' => 'member',
                    'status' => 'pending',
                    'join_message' => $validated['join_message'] ?? null,
                    'is_active' => false
                ]);
                
                // Notify channel admins
                $this->notifyChannelAdmins($channel, $user, 'join_request');
                
                return response()->json([
                    'message' => 'Join request submitted successfully',
                    'membership' => $membership
                ], 201);
            } else {
                // If channel doesn't require approval, add as member immediately
                $membership = ChannelMember::create([
                    'channel_id' => $channel->id,
                    'user_id' => $user->id,
                    'role' => 'member',
                    'status' => 'approved',
                    'is_active' => true,
                    'joined_at' => now()
                ]);
                
                // Load relations for response
                $channel->load(['messages.sender', 'members.user', 'creator']);
                $channel->user_role = 'member';
                
                return response()->json([
                    'message' => 'Successfully joined the channel',
                    'channel' => $channel
                ], 201);
            }
        } catch (\Exception $e) {
            Log::error('Failed to join channel: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to join channel',
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
            'share_link' => 'required|string',
            'join_message' => 'nullable|string|max:1000'
        ]);
        
        // Find channel by share link
        $channel = Channel::where('share_link', $validated['share_link'])->first();
        
        if (!$channel) {
            return response()->json(['message' => 'Invalid share link'], 404);
        }
        
        // Use the same logic as join with code
        $request->merge(['join_code' => $channel->join_code]);
        return $this->joinWithCode($request);
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
            'share_link' => $channel->share_link,
            'join_code' => $channel->join_code
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
    
    /**
     * Regenerate the join code for a channel
     */
    public function regenerateJoinCode($id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is an admin
        if (!$channel->isAdmin($user)) {
            return response()->json(['message' => 'Only channel admins can regenerate the join code'], 403);
        }
        
        try {
            // Generate new join code
            $joinCode = $channel->regenerateJoinCode();
            
            return response()->json([
                'message' => 'Join code regenerated',
                'join_code' => $joinCode
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to regenerate join code: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to regenerate join code',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Hire an educator from the channel
     */
    public function hireEducator(Request $request, $id, $educatorId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Check if user is a learner
        if ($user->role !== User::ROLE_LEARNER) {
            return response()->json(['message' => 'Only learners can hire educators'], 403);
        }
        
        // Validate request
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'rate' => 'required|numeric|min:0',
            'hours' => 'required|integer|min:1',
            'schedule' => 'required|string|max:1000',
            'subject' => 'required|string|max:255'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($id);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get the educator
        $educator = User::findOrFail($educatorId);
        
        // Check if educator is a member of the channel
        if (!$channel->isMember($educator)) {
            return response()->json(['message' => 'The educator is not a member of this channel'], 403);
        }
        
        // Check if educator is actually an educator
        if ($educator->role !== User::ROLE_EDUCATOR) {
            return response()->json(['message' => 'The selected user is not an educator'], 400);
        }
        
        // Check if educator can be hired
        if (!$educator->canBeHired()) {
            return response()->json(['message' => 'This educator cannot be hired. They may need to be verified first.'], 400);
        }
        
        try {
            // Create hire request
            $hireRequest = HireRequest::create([
                'client_id' => $user->id,
                'tutor_id' => $educator->id,
                'message' => $validated['message'],
                'amount' => $validated['rate'],
                'hours' => $validated['hours'],
                'schedule' => $validated['schedule'],
                'subject' => $validated['subject'],
                'status' => 'pending',
                'reference' => 'channel_' . $channel->id
            ]);
            
            // Notify educator about the request
            $educator->notify(new \App\Notifications\HireRequestNotification($hireRequest));
            
            return response()->json([
                'message' => 'Hire request sent successfully',
                'hire_request' => $hireRequest
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to send hire request: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to send hire request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Notify all channel admins about an event
     */
    private function notifyChannelAdmins(Channel $channel, User $user, $type)
    {
        // Get all channel admins
        $admins = $channel->members()
            ->where('role', 'admin')
            ->where('status', 'approved')
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->pluck('user');
        
        $notificationData = [
            'channel_id' => $channel->id,
            'channel_title' => $channel->title,
            'user_id' => $user->id,
            'username' => $user->username
        ];
        
        if ($type === 'join_request') {
            Notification::send($admins, new BaseNotification(
                $user->username . ' has requested to join your channel ' . $channel->title,
                'channel_join_request',
                'info',
                $notificationData
            ));
        }
    }
}