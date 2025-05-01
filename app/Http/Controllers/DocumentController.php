<?php

namespace App\Http\Controllers;

use App\Events\CollaborationOperation;
use App\Models\Channel;
use App\Models\CollaborativeContent;
use App\Models\CollaborativeSpace;
use App\Models\ContentVersion;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * Get all documents in a channel
     */
    public function getDocuments($channelId)
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
        
        // Find all document spaces in this channel
        $documentSpaces = $channel->collaborativeSpaces()
            ->where('type', 'document')
            ->with(['creator', 'contents' => function($query) {
                $query->latest('updated_at')->limit(1);
            }])
            ->orderBy('updated_at', 'desc')
            ->get();
        
        // Prepare response with documents and user permissions
        $documents = $documentSpaces->map(function($space) use ($user) {
            $document = [
                'id' => $space->id,
                'title' => $space->title,
                'description' => $space->description,
                'created_at' => $space->created_at,
                'updated_at' => $space->updated_at,
                'created_by' => $space->creator->name,
                'can_edit' => $space->canEdit($user)
            ];
            
            // Get last modified timestamp from the most recent content
            if ($space->contents->isNotEmpty()) {
                $document['last_modified'] = $space->contents->first()->updated_at;
            }
            
            return $document;
        });
        
        return response()->json([
            'documents' => $documents
        ]);
    }
    
    /**
     * Create a new document in a channel
     */
    public function createDocument(Request $request, $channelId)
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
            'content' => 'nullable|string',
            'description' => 'nullable|string|max:1000',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Create a document space
            $space = CollaborativeSpace::create([
                'channel_id' => $channel->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'type' => 'document',
                'settings' => null,
                'created_by' => $user->id
            ]);
            
            // Initial content can be empty, supplied content, or a template
            $initialContent = $validated['content'] ?? '';
            if (empty($initialContent)) {
                $initialContent = '<h1>' . $validated['title'] . '</h1><p>Start typing here...</p>';
            }
            
            // Create the document content
            $content = CollaborativeContent::create([
                'space_id' => $space->id,
                'content_type' => 'text/html', // Using HTML for rich text
                'content_data' => $initialContent,
                'metadata' => [
                    'title' => $validated['title']
                ],
                'created_by' => $user->id
            ]);
            
            // Create initial version
            ContentVersion::create([
                'content_id' => $content->id,
                'content_data' => $content->content_data,
                'version_number' => 1,
                'created_by' => $user->id,
                'metadata' => $content->metadata
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Document created successfully',
                'document' => [
                    'id' => $space->id,
                    'content_id' => $content->id,
                    'title' => $space->title,
                    'content' => $content->content_data,
                    'created_at' => $space->created_at,
                    'updated_at' => $space->updated_at
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create document: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to create document',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get a specific document
     */
    public function getDocument($channelId, $documentId)
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
        
        // Get document space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->where('id', $documentId)
            ->with(['creator', 'contents' => function($query) {
                $query->latest('updated_at')->first();
            }])
            ->firstOrFail();
        
        // Check if it's a document
        if ($space->type !== 'document') {
            return response()->json(['message' => 'This is not a document'], 400);
        }
        
        // Get the latest content
        $content = $space->contents->first();
        if (!$content) {
            return response()->json(['message' => 'Document content not found'], 404);
        }
        
        // Get collaborators (active users)
        $activeUsers = Operation::where('content_id', $content->id)
            ->where('type', 'cursor')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->with('user:id,name,avatar')
            ->get()
            ->pluck('user')
            ->unique('id')
            ->values();
        
        // Prepare the document response
        $document = [
            'id' => $space->id,
            'content_id' => $content->id,
            'title' => $space->title,
            'description' => $space->description,
            'content' => $content->content_data,
            'version' => $content->version,
            'metadata' => $content->metadata,
            'created_at' => $space->created_at,
            'updated_at' => $content->updated_at,
            'created_by' => [
                'id' => $space->creator->id,
                'name' => $space->creator->name
            ],
            'collaborators' => $activeUsers,
            'can_edit' => $content->canEdit($user),
            'can_comment' => $content->canComment($user)
        ];
        
        return response()->json([
            'document' => $document
        ]);
    }
    
    /**
     * Update document content
     */
    public function updateDocumentContent(Request $request, $channelId, $documentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'content' => 'required|string',
            'create_version' => 'nullable|boolean'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get document space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->where('id', $documentId)
            ->firstOrFail();
        
        // Check if it's a document
        if ($space->type !== 'document') {
            return response()->json(['message' => 'This is not a document'], 400);
        }
        
        // Get the latest content
        $content = $space->contents()->latest()->first();
        if (!$content) {
            return response()->json(['message' => 'Document content not found'], 404);
        }
        
        // Check if user can edit
        if (!$content->canEdit($user)) {
            return response()->json(['message' => 'You do not have permission to edit this document'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Check if we should create a new version
            $createVersion = $validated['create_version'] ?? false;
            if ($createVersion) {
                // Create a new version record
                ContentVersion::create([
                    'content_id' => $content->id,
                    'content_data' => $content->content_data,
                    'version_number' => $content->version,
                    'created_by' => $user->id,
                    'metadata' => $content->metadata
                ]);
                
                // Increment version number
                $content->version++;
            }
            
            // Update content
            $content->content_data = $validated['content'];
            $content->updated_at = now();
            $content->save();
            
            // Update space updated_at time
            $space->touch();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Document content updated successfully',
                'version' => $content->version,
                'updated_at' => $content->updated_at
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update document content: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update document content',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update document title
     */
    public function updateDocumentTitle(Request $request, $channelId, $documentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'title' => 'required|string|max:255'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get document space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->where('id', $documentId)
            ->firstOrFail();
        
        // Check if user can edit
        if (!$space->canEdit($user)) {
            return response()->json(['message' => 'You do not have permission to edit this document'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Update space title
            $space->title = $validated['title'];
            $space->save();
            
            // Update the metadata in content
            $content = $space->contents()->latest()->first();
            if ($content) {
                $metadata = $content->metadata ?? [];
                $metadata['title'] = $validated['title'];
                $content->metadata = $metadata;
                $content->save();
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Document title updated successfully',
                'title' => $space->title
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update document title: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update document title',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get document collaborators
     */
    public function getDocumentCollaborators($channelId, $documentId)
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
        
        // Get document space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->where('id', $documentId)
            ->firstOrFail();
        
        // Get the content
        $content = $space->contents()->latest()->first();
        if (!$content) {
            return response()->json(['message' => 'Document content not found'], 404);
        }
        
        // Get active collaborators (users with cursor operations in the last 5 minutes)
        $activeCollaborators = Operation::where('content_id', $content->id)
            ->where('type', 'cursor')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->with('user:id,name,avatar')
            ->get()
            ->pluck('user')
            ->unique('id')
            ->values();
        
        // Get users with permissions on this content
        $permissionUsers = $content->permissions()
            ->whereNotNull('user_id')
            ->with('user:id,name,avatar')
            ->get()
            ->map(function($permission) {
                return [
                    'id' => $permission->user->id,
                    'name' => $permission->user->name,
                    'avatar' => $permission->user->avatar,
                    'role' => $permission->role,
                    'is_active' => false // Default to inactive
                ];
            })
            ->keyBy('id');
        
        // Mark active users
        foreach ($activeCollaborators as $activeUser) {
            if (isset($permissionUsers[$activeUser->id])) {
                $permissionUsers[$activeUser->id]['is_active'] = true;
            } else {
                // Add active user even if they don't have explicit permissions
                // (they might be viewing due to channel membership)
                $permissionUsers[$activeUser->id] = [
                    'id' => $activeUser->id,
                    'name' => $activeUser->name,
                    'avatar' => $activeUser->avatar,
                    'role' => 'viewer', // Default role for channel members
                    'is_active' => true
                ];
            }
        }
        
        return response()->json([
            'collaborators' => array_values($permissionUsers->toArray())
        ]);
    }
    
    /**
     * Get document version history
     */
    public function getDocumentHistory($channelId, $documentId)
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
        
        // Get document space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->where('id', $documentId)
            ->firstOrFail();
        
        // Get the content
        $content = $space->contents()->latest()->first();
        if (!$content) {
            return response()->json(['message' => 'Document content not found'], 404);
        }
        
        // Get versions with creators
        $versions = ContentVersion::where('content_id', $content->id)
            ->with('creator:id,name,avatar')
            ->orderBy('version_number', 'desc')
            ->get()
            ->map(function($version) {
                return [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                    'created_at' => $version->created_at,
                    'created_by' => [
                        'id' => $version->creator->id,
                        'name' => $version->creator->name,
                        'avatar' => $version->creator->avatar
                    ]
                ];
            });
        
        return response()->json([
            'versions' => $versions,
            'current_version' => $content->version
        ]);
    }
    
    /**
     * Restore document to a previous version
     */
    public function restoreDocumentVersion(Request $request, $channelId, $documentId, $versionId)
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
        
        // Get document space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->where('id', $documentId)
            ->firstOrFail();
        
        // Get the content
        $content = $space->contents()->latest()->first();
        if (!$content) {
            return response()->json(['message' => 'Document content not found'], 404);
        }
        
        // Check if user can edit
        if (!$content->canEdit($user)) {
            return response()->json(['message' => 'You do not have permission to edit this document'], 403);
        }
        
        // Get the version to restore
        $version = ContentVersion::where('id', $versionId)
            ->where('content_id', $content->id)
            ->firstOrFail();
        
        try {
            DB::beginTransaction();
            
            // Create a new version of the current state before restoring
            ContentVersion::create([
                'content_id' => $content->id,
                'content_data' => $content->content_data,
                'version_number' => $content->version,
                'created_by' => $user->id,
                'metadata' => $content->metadata
            ]);
            
            // Restore the content to the selected version
            $content->content_data = $version->content_data;
            $content->metadata = $version->metadata;
            $content->version++; // Increment version
            $content->save();
            
            // Update space updated_at time
            $space->touch();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Document restored to version ' . $version->version_number,
                'content' => $content->content_data,
                'version' => $content->version,
                'updated_at' => $content->updated_at
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore document version: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to restore document version',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Process document operation for real-time editing
     */
    public function processDocumentOperation(Request $request, $channelId, $documentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'type' => 'required|string|in:insert,delete,format',
            'position' => 'required|integer|min:0',
            'length' => 'nullable|integer|min:0',
            'text' => 'nullable|string',
            'version' => 'required|integer|min:1',
            'meta' => 'nullable|array'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get document space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->where('id', $documentId)
            ->firstOrFail();
        
        // Get the content
        $content = $space->contents()->latest()->first();
        if (!$content) {
            return response()->json(['message' => 'Document content not found'], 404);
        }
        
        // Check if user can edit
        if (!$content->canEdit($user)) {
            return response()->json(['message' => 'You do not have permission to edit this document'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // Check if versions match
            $clientVersion = $validated['version'];
            if ($clientVersion !== $content->version) {
                return response()->json([
                    'message' => 'Version mismatch',
                    'client_version' => $clientVersion,
                    'server_version' => $content->version,
                    'content' => $content->content_data
                ], 409); // Conflict
            }
            
            // Create operation record
            $operation = Operation::create([
                'content_id' => $content->id,
                'user_id' => $user->id,
                'type' => $validated['type'],
                'position' => $validated['position'],
                'length' => $validated['length'] ?? 0,
                'text' => $validated['text'] ?? '',
                'version' => $content->version,
                'meta' => $validated['meta'] ?? null
            ]);
            
            // Apply operation to content
            if ($operation->type === 'insert' || $operation->type === 'delete') {
                $newContentData = $operation->apply($content->content_data);
                $content->content_data = $newContentData;
                $content->version++; // Increment version
                $content->save();
                
                // Update space updated_at time
                $space->touch();
            }
            
            // Broadcast the operation to all connected clients
            broadcast(new CollaborationOperation(
                $operation,
                $content,
                $channelId,
                $documentId,
                $user
            ));
            
            DB::commit();
            
            return response()->json([
                'message' => 'Operation processed successfully',
                'operation_id' => $operation->id,
                'version' => $content->version
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process document operation: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to process document operation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update cursor position in document
     */
    public function updateDocumentCursor(Request $request, $channelId, $documentId)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        
        // Validate request
        $validated = $request->validate([
            'position' => 'required|integer|min:0',
            'selection_start' => 'nullable|integer|min:0',
            'selection_end' => 'nullable|integer|min:0',
            'color' => 'nullable|string'
        ]);
        
        // Get channel
        $channel = Channel::findOrFail($channelId);
        
        // Check if user is a member
        if (!$channel->isMember($user)) {
            return response()->json(['message' => 'You are not a member of this channel'], 403);
        }
        
        // Get document space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->where('id', $documentId)
            ->firstOrFail();
        
        // Get the content
        $content = $space->contents()->latest()->first();
        if (!$content) {
            return response()->json(['message' => 'Document content not found'], 404);
        }
        
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
                'meta' => [
                    'color' => $validated['color'] ?? '#' . substr(md5($user->id), 0, 6),
                    'selection_start' => $validated['selection_start'] ?? null,
                    'selection_end' => $validated['selection_end'] ?? null,
                ]
            ]);
            
            // Broadcast the cursor update
            broadcast(new CollaborationOperation(
                $operation,
                $content,
                $channelId,
                $documentId,
                $user,
                true // isCursor flag
            ));
            
            return response()->json([
                'message' => 'Cursor position updated',
                'operation_id' => $operation->id
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
     * Get cursor positions for all collaborators
     */
    public function getDocumentCursors($channelId, $documentId)
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
        
        // Get document space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->where('id', $documentId)
            ->firstOrFail();
        
        // Get the content
        $content = $space->contents()->latest()->first();
        if (!$content) {
            return response()->json(['message' => 'Document content not found'], 404);
        }
        
        // Get the latest cursor/selection operation for each active user (within last 5 minutes)
        $cursors = Operation::where('content_id', $content->id)
            ->whereIn('type', ['cursor', 'selection'])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->whereNot('user_id', $user->id) // Exclude current user
            ->with('user:id,name,avatar')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('user_id') // Get only latest for each user
            ->values()
            ->map(function($operation) {
                return [
                    'user' => [
                        'id' => $operation->user->id,
                        'name' => $operation->user->name,
                        'avatar' => $operation->user->avatar
                    ],
                    'type' => $operation->type,
                    'position' => $operation->position,
                    'length' => $operation->length,
                    'meta' => $operation->meta
                ];
            });
        
        return response()->json([
            'cursors' => $cursors
        ]);
    }
    
    /**
     * Update document permissions
     */
    public function updateDocumentPermissions(Request $request, $channelId, $documentId)
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
        
        // Get document space
        $space = CollaborativeSpace::where('channel_id', $channel->id)
            ->where('id', $documentId)
            ->firstOrFail();
        
        // Check if user is space owner or channel admin
        if ($space->created_by !== $user->id && !$channel->isAdmin($user)) {
            return response()->json(['message' => 'You do not have permission to update document permissions'], 403);
        }
        
        // Get the content
        $content = $space->contents()->latest()->first();
        if (!$content) {
            return response()->json(['message' => 'Document content not found'], 404);
        }
        
        try {
            DB::beginTransaction();
            
            // Clear existing permissions except owner
            $content->permissions()->where('role', '!=', 'owner')->delete();
            
            // Add new permissions
            foreach ($validated['permissions'] as $permission) {
                // Skip if trying to change owner
                if ($permission['role'] === 'owner') {
                    continue;
                }
                
                $content->permissions()->create([
                    'user_id' => $permission['user_id'],
                    'role' => $permission['role'],
                    'granted_by' => $user->id
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Document permissions updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update document permissions: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update document permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}