<?php

namespace App\Http\Controllers;

use App\Events\CollaborationOperation as CollaborationOperationEvent;
use App\Events\CommentAdded;
use App\Events\ContentUpdated;
use App\Models\Channel;
use App\Models\CollaborativeContent;
use App\Models\CollaborativeSpace;
use App\Models\ContentComment;
use App\Models\ContentPermission;
use App\Models\ContentVersion;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CollaborationController extends Controller
{
    /**
     * Get all spaces in a channel
     */
    public function getSpaces($channelId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get all spaces
        $spaces = $channel->collaborativeSpaces()
            ->with('creator')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($space) use ($user) {
                // Add user permissions
                $space->can_edit = $space->canEdit($user);
                return $space;
            });
        
        return response()->json([
            'spaces' => $spaces
        ]);
    }
    
    /**
     * Create a new collaborative space
     */
    public function createSpace(Request $request, $channelId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Validate request
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|string|in:document,whiteboard,code,video', // Supported space types
            'settings' => 'nullable|array'
        ]);
        
        try {
            DB::beginTransaction();
            
            // Create collaborative space
            $space = CollaborativeSpace::create([
                'channel_id' => $channel->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'settings' => $validated['settings'] ?? null,
                'created_by' => $user->id
            ]);
            
            // Create initial content based on type
            $initialContent = $this->createInitialContent($space, $user);
            
            // Set default permissions (everyone in channel can view)
            ContentPermission::create([
                'content_id' => $initialContent->id,
                'user_id' => null, // Null means applies to all users
                'role' => 'viewer',
                'granted_by' => $user->id
            ]);
            
            // Creator gets owner permission
            ContentPermission::create([
                'content_id' => $initialContent->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'granted_by' => $user->id
            ]);
            
            // Channel admins get editor permission
            $adminMembers = $channel->members()
                ->where('role', 'admin')
                ->where('status', 'approved')
                ->where('is_active', true)
                ->where('user_id', '!=', $user->id) // Skip creator who already has owner permission
                ->get();
                
            foreach ($adminMembers as $adminMember) {
                ContentPermission::create([
                    'content_id' => $initialContent->id,
                    'user_id' => $adminMember->user_id,
                    'role' => 'editor',
                    'granted_by' => $user->id
                ]);
            }
            
            DB::commit();
            
            // Load relationships for response
            $space->load(['creator', 'contents', 'contents.permissions']);
            $space->can_edit = true; // Creator can always edit
            
            return response()->json([
                'message' => 'Collaborative space created successfully',
                'space' => $space
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create collaborative space: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to create collaborative space',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get a specific collaborative space
     */
    public function getSpace($channelId, $spaceId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::with(['creator', 'contents.creator', 'contents.permissions'])
            ->where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Add user permissions
        $space->can_edit = $space->canEdit($user);
        
        // Add permission details for each content
        $space->contents->each(function ($content) use ($user) {
            $content->can_edit = $content->canEdit($user);
            $content->can_comment = $content->canComment($user);
        });
        
        return response()->json([
            'space' => $space
        ]);
    }
    
    /**
     * Update a collaborative space
     */
    public function updateSpace(Request $request, $channelId, $spaceId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Check if user can edit the space
        if (!$space->canEdit($user)) {
            return response()->json(['message' => 'You do not have permission to edit this space'], 403);
        }
        
        // Validate request
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'settings' => 'nullable|array'
        ]);
        
        try {
            // Update space
            $space->update($validated);
            
            return response()->json([
                'message' => 'Collaborative space updated successfully',
                'space' => $space
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update collaborative space: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update collaborative space',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a collaborative space
     */
    public function deleteSpace($channelId, $spaceId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Check if user is creator or channel admin
        if ($space->created_by !== $user->id && !$channel->isAdmin($user)) {
            return response()->json(['message' => 'You do not have permission to delete this space'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Delete all contents, permissions, versions, and comments
            foreach ($space->contents as $content) {
                $content->permissions()->delete();
                $content->versions()->delete();
                $content->comments()->delete();
                $content->delete();
            }
            
            // Delete space
            $space->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Collaborative space deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete collaborative space: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to delete collaborative space',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get content from a collaborative space
     */
    public function getContent($channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::with(['creator', 'versions', 'permissions', 'comments.user', 'comments.replies.user'])
            ->where('space_id', $space->id)
            ->findOrFail($contentId);
            
        // Check if user can view content
        if (!$content->canView($user)) {
            return response()->json(['message' => 'You do not have permission to view this content'], 403);
        }
        
        // Add permission flags
        $content->can_edit = $content->canEdit($user);
        $content->can_comment = $content->canComment($user);
        
        return response()->json([
            'content' => $content
        ]);
    }
    
    /**
     * Create new content in a collaborative space
     */
    public function createContent(Request $request, $channelId, $spaceId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Check if user can edit space
        if (!$space->canEdit($user)) {
            return response()->json(['message' => 'You do not have permission to add content to this space'], 403);
        }
        
        // Validate request
        $validated = $request->validate([
            'content_type' => 'required|string',
            'content_data' => 'required|string',
            'metadata' => 'nullable|array'
        ]);
        
        try {
            DB::beginTransaction();
            
            // Create content
            $content = CollaborativeContent::create([
                'space_id' => $space->id,
                'content_type' => $validated['content_type'],
                'content_data' => $validated['content_data'],
                'metadata' => $validated['metadata'] ?? null,
                'created_by' => $user->id
            ]);
            
            // Set default permissions (everyone in channel can view)
            ContentPermission::create([
                'content_id' => $content->id,
                'user_id' => null, // Null means applies to all users
                'role' => 'viewer',
                'granted_by' => $user->id
            ]);
            
            // Creator gets owner permission
            ContentPermission::create([
                'content_id' => $content->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'granted_by' => $user->id
            ]);
            
            DB::commit();
            
            // Load relationships
            $content->load(['creator', 'permissions']);
            $content->can_edit = true; // Creator can always edit
            $content->can_comment = true;
            
            return response()->json([
                'message' => 'Content created successfully',
                'content' => $content
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create content: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to create content',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update content
     */
    public function updateContent(Request $request, $channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Check if user can edit content
        if (!$content->canEdit($user)) {
            return response()->json(['message' => 'You do not have permission to edit this content'], 403);
        }
        
        // Validate request
        $validated = $request->validate([
            'content_data' => 'required|string',
            'metadata' => 'nullable|array',
            'operation' => 'nullable|array' // For operational transforms
        ]);
        
        try {
            DB::beginTransaction();
            
            // Update content
            $content->content_data = $validated['content_data'];
            if (isset($validated['metadata'])) {
                $content->metadata = $validated['metadata'];
            }
            $content->save();
            
            // If operation data was provided, record it for OT
            if (isset($validated['operation']) && is_array($validated['operation'])) {
                $operation = Operation::create([
                    'content_id' => $content->id,
                    'user_id' => $user->id,
                    'type' => $validated['operation']['type'] ?? 'insert',
                    'position' => $validated['operation']['position'] ?? 0,
                    'length' => $validated['operation']['length'] ?? 0,
                    'text' => $validated['operation']['text'] ?? '',
                    'version' => $content->version,
                    'meta' => $validated['operation']['meta'] ?? null
                ]);
                
                // Broadcast the operation to all users
                broadcast(new CollaborationOperationEvent(
                    $operation, 
                    $content, 
                    $channelId, 
                    $spaceId, 
                    $user
                ));
            } else {
                // Broadcast content updated event
                broadcast(new ContentUpdated(
                    $content, 
                    $channelId, 
                    $spaceId, 
                    $user, 
                    'update', 
                    ['metadata' => $content->metadata]
                ));
            }
            
            DB::commit();
            
            // Load relationships
            $content->load(['creator', 'versions', 'permissions']);
            $content->can_edit = true;
            $content->can_comment = true;
            
            return response()->json([
                'message' => 'Content updated successfully',
                'content' => $content
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update content: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update content',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete content
     */
    public function deleteContent($channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Check if user is creator or space creator or channel admin
        if ($content->created_by !== $user->id && $space->created_by !== $user->id && !$channel->isAdmin($user)) {
            return response()->json(['message' => 'You do not have permission to delete this content'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Delete permissions, versions, and comments
            $content->permissions()->delete();
            $content->versions()->delete();
            $content->comments()->delete();
            
            // Delete content
            $content->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Content deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete content: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to delete content',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get content versions
     */
    public function getContentVersions($channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Check if user can view content
        if (!$content->canView($user)) {
            return response()->json(['message' => 'You do not have permission to view this content'], 403);
        }
        
        // Get versions
        $versions = $content->versions()->with('creator')->get();
        
        return response()->json([
            'versions' => $versions
        ]);
    }
    
    /**
     * Restore content to a specific version
     */
    public function restoreVersion(Request $request, $channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'version_id' => 'required|string|exists:content_versions,id'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Check if user can edit content
        if (!$content->canEdit($user)) {
            return response()->json(['message' => 'You do not have permission to edit this content'], 403);
        }
        
        // Get version
        $version = ContentVersion::where('content_id', $content->id)
            ->findOrFail($validated['version_id']);
        
        try {
            // Update content with version data
            $content->content_data = $version->content_data;
            $content->save();
            
            // Load relationships
            $content->load(['creator', 'versions', 'permissions']);
            
            return response()->json([
                'message' => 'Content restored to version ' . $version->version_number,
                'content' => $content
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore version: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to restore version',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add a comment to content
     */
    public function addComment(Request $request, $channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'comment_text' => 'required|string|max:2000',
            'position' => 'nullable|array',
            'parent_id' => 'nullable|string|exists:content_comments,id'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Check if user can comment on content
        if (!$content->canComment($user)) {
            return response()->json(['message' => 'You do not have permission to comment on this content'], 403);
        }
        
        // If parent_id is provided, check if it belongs to this content
        if (!empty($validated['parent_id'])) {
            $parentExists = ContentComment::where('id', $validated['parent_id'])
                ->where('content_id', $content->id)
                ->exists();
                
            if (!$parentExists) {
                return response()->json(['message' => 'Invalid parent comment'], 400);
            }
        }
        
        try {
            DB::beginTransaction();
            
            // Create comment
            $comment = ContentComment::create([
                'content_id' => $content->id,
                'user_id' => $user->id,
                'comment_text' => $validated['comment_text'],
                'position' => $validated['position'] ?? null,
                'parent_id' => $validated['parent_id'] ?? null
            ]);
            
            // Load user relationship
            $comment->load('user');
            
            // Broadcast the comment to all users
            broadcast(new CommentAdded(
                $comment,
                $channelId,
                $spaceId,
                $contentId,
                $user
            ));
            
            DB::commit();
            
            return response()->json([
                'message' => 'Comment added successfully',
                'comment' => $comment
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add comment: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to add comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update a comment
     */
    public function updateComment(Request $request, $channelId, $spaceId, $contentId, $commentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'comment_text' => 'required|string|max:2000'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Get comment
        $comment = ContentComment::where('content_id', $content->id)
            ->findOrFail($commentId);
        
        // Check if user is the comment author or a channel admin
        if ($comment->user_id !== $user->id && !$channel->isAdmin($user)) {
            return response()->json(['message' => 'You do not have permission to update this comment'], 403);
        }
        
        try {
            // Update comment
            $comment->comment_text = $validated['comment_text'];
            $comment->save();
            
            // Load user relationship
            $comment->load('user');
            
            return response()->json([
                'message' => 'Comment updated successfully',
                'comment' => $comment
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update comment: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a comment
     */
    public function deleteComment($channelId, $spaceId, $contentId, $commentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Get comment
        $comment = ContentComment::where('content_id', $content->id)
            ->findOrFail($commentId);
        
        // Check if user is the comment author, content creator, or a channel admin
        if ($comment->user_id !== $user->id && $content->created_by !== $user->id && !$channel->isAdmin($user)) {
            return response()->json(['message' => 'You do not have permission to delete this comment'], 403);
        }
        
        try {
            // Delete replies first
            $comment->replies()->delete();
            
            // Delete comment
            $comment->delete();
            
            return response()->json([
                'message' => 'Comment deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete comment: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Resolve or unresolve a comment
     */
    public function resolveComment(Request $request, $channelId, $spaceId, $contentId, $commentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'resolved' => 'required|boolean'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Get comment
        $comment = ContentComment::where('content_id', $content->id)
            ->findOrFail($commentId);
        
        // Check if user can edit content or is comment author or channel admin
        if (!$content->canEdit($user) && $comment->user_id !== $user->id && !$channel->isAdmin($user)) {
            return response()->json(['message' => 'You do not have permission to resolve/unresolve this comment'], 403);
        }
        
        try {
            // Update comment resolved status
            $comment->resolved = $validated['resolved'];
            $comment->save();
            
            // Load user relationship
            $comment->load('user');
            
            return response()->json([
                'message' => $validated['resolved'] ? 'Comment resolved successfully' : 'Comment unresolved successfully',
                'comment' => $comment
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update comment resolved status: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update comment resolved status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get all comments for content
     */
    public function getComments($channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Check if user can view content
        if (!$content->canView($user)) {
            return response()->json(['message' => 'You do not have permission to view this content'], 403);
        }
        
        // Get comments with replies and users
        $comments = $content->comments()->with(['user', 'replies.user'])->get();
        
        return response()->json([
            'comments' => $comments
        ]);
    }
    
    /**
     * Process an operation for real-time collaboration
     */
    public function processOperation(Request $request, $channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Check if user can edit content
        if (!$content->canEdit($user)) {
            return response()->json(['message' => 'You do not have permission to edit this content'], 403);
        }
        
        // Validate request
        $validated = $request->validate([
            'type' => 'required|string|in:insert,delete,format,cursor,selection',
            'position' => 'required|integer|min:0',
            'length' => 'nullable|integer|min:0',
            'text' => 'nullable|string',
            'version' => 'required|integer|min:1',
            'meta' => 'nullable|array',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Check if version matches current content version
            $clientVersion = $validated['version'];
            $serverVersion = $content->version;
            
            // Get all operations that happened since the client version
            $operationsSinceClientVersion = Operation::where('content_id', $content->id)
                ->where('version', '>=', $clientVersion)
                ->orderBy('version', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();
            
            // Create the operation
            $operation = new Operation([
                'content_id' => $content->id,
                'user_id' => $user->id,
                'type' => $validated['type'],
                'position' => $validated['position'],
                'length' => $validated['length'] ?? 0,
                'text' => $validated['text'] ?? '',
                'version' => $clientVersion,
                'meta' => $validated['meta'] ?? null
            ]);
            
            // If there are new operations, transform this operation
            foreach ($operationsSinceClientVersion as $existingOp) {
                $operation = $operation->transform($existingOp);
            }
            
            // If it's an insert or delete, apply to content
            if ($operation->type === 'insert' || $operation->type === 'delete') {
                $newContent = $operation->apply($content->content_data);
                $content->content_data = $newContent;
                $content->version = $serverVersion + 1;
                $content->save();
            }
            
            // Update the operation's version to the current server version
            $operation->version = $content->version;
            $operation->save();
            
            // Broadcast the operation to all users
            broadcast(new CollaborationOperationEvent(
                $operation, 
                $content, 
                $channelId, 
                $spaceId, 
                $user
            ));
            
            DB::commit();
            
            return response()->json([
                'message' => 'Operation processed successfully',
                'operation' => $operation,
                'content_version' => $content->version,
                'transformed' => $operationsSinceClientVersion->count() > 0
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process operation: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to process operation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get cursor positions from other users
     */
    public function getCursors($channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Check if user can view content
        if (!$content->canView($user)) {
            return response()->json(['message' => 'You do not have permission to view this content'], 403);
        }
        
        // Get the latest cursor/selection operation for each user
        $cursors = Operation::where('content_id', $content->id)
            ->whereIn('type', ['cursor', 'selection'])
            ->whereNot('user_id', $user->id) // Exclude current user
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('user_id') // Get only the latest for each user
            ->values(); // Reset the array keys
        
        // Load user details
        $cursors->load('user:id,username,avatar,role');
        
        return response()->json([
            'cursors' => $cursors
        ]);
    }
    
    /**
     * Update cursor position
     */
    public function updateCursor(Request $request, $channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Check if user can view content (minimum requirement for cursor position)
        if (!$content->canView($user)) {
            return response()->json(['message' => 'You do not have permission to view this content'], 403);
        }
        
        // Validate request
        $validated = $request->validate([
            'position' => 'required|integer|min:0',
            'selection_start' => 'nullable|integer|min:0',
            'selection_end' => 'nullable|integer|min:0',
            'color' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);
        
        try {
            // Create a cursor operation
            $operation = Operation::create([
                'content_id' => $content->id,
                'user_id' => $user->id,
                'type' => isset($validated['selection_start']) ? 'selection' : 'cursor',
                'position' => $validated['position'],
                'length' => isset($validated['selection_end']) ? ($validated['selection_end'] - $validated['selection_start']) : 0,
                'text' => '',
                'version' => $content->version,
                'meta' => array_merge($validated['meta'] ?? [], [
                    'color' => $validated['color'] ?? '#' . substr(md5($user->id), 0, 6),
                    'selection_start' => $validated['selection_start'] ?? null,
                    'selection_end' => $validated['selection_end'] ?? null,
                ])
            ]);
            
            // Broadcast the cursor update
            broadcast(new CollaborationOperationEvent(
                $operation,
                $content,
                $channelId,
                $spaceId,
                $user
            ));
            
            return response()->json([
                'message' => 'Cursor position updated',
                'operation' => $operation
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update cursor position: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update cursor position',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update permissions for content
     */
    public function updatePermissions(Request $request, $channelId, $spaceId, $contentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.user_id' => 'nullable|string|exists:users,id',
            'permissions.*.role' => 'required|string|in:owner,editor,commenter,viewer'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->findOrFail($spaceId);
        
        // Get content
        $content = CollaborativeContent::where('space_id', $space->id)
            ->findOrFail($contentId);
        
        // Check if user is owner of content or space creator or channel admin
        $permission = $content->getPermissionFor($user);
        if (!$permission || $permission->role !== 'owner' && $space->created_by !== $user->id && !$channel->isAdmin($user)) {
            return response()->json(['message' => 'Only content owners can update permissions'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Delete all existing permissions
            $content->permissions()->delete();
            
            // Create new permissions
            foreach ($validated['permissions'] as $permData) {
                ContentPermission::create([
                    'content_id' => $content->id,
                    'user_id' => $permData['user_id'] ?? null,
                    'role' => $permData['role'],
                    'granted_by' => $user->id
                ]);
            }
            
            DB::commit();
            
            // Load updated permissions
            $content->load('permissions.user');
            
            return response()->json([
                'message' => 'Permissions updated successfully',
                'permissions' => $content->permissions
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update permissions: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create initial content for a collaborative space based on its type
     */
    private function createInitialContent($space, $user)
    {
        $contentData = '';
        $contentType = 'text/plain';
        $metadata = [];
        
        switch ($space->type) {
            case 'document':
                $contentData = '<h1>' . $space->title . '</h1><p>Start typing your content here...</p>';
                $contentType = 'text/html';
                break;
                
            case 'whiteboard':
                $contentData = '[]'; // Empty array for whiteboard objects
                $contentType = 'application/json';
                $metadata = [
                    'canvas_width' => 1920,
                    'canvas_height' => 1080,
                    'zoom' => 1.0
                ];
                break;
                
            case 'code':
                $contentData = '// ' . $space->title . "\n\n// Write your code here";
                $contentType = 'text/plain';
                $metadata = [
                    'language' => 'javascript',
                    'line_numbers' => true,
                    'tab_size' => 2
                ];
                break;
                
            case 'video':
                $contentData = '{}'; // Empty JSON object for video project
                $contentType = 'application/json';
                $metadata = [
                    'format' => 'mp4',
                    'resolution' => '1080p',
                    'fps' => 30
                ];
                break;
        }
        
        return CollaborativeContent::create([
            'space_id' => $space->id,
            'content_type' => $contentType,
            'content_data' => $contentData,
            'metadata' => $metadata,
            'created_by' => $user->id
        ]);
    }
}