<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Services\FileUploadService;

class PostController extends Controller
{
    protected $fileUploadService;

    /**
     * Create a new controller instance.
     * 
     * @param FileUploadService $fileUploadService
     */
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Display a listing of posts.
     */
    public function index()
    {
        $posts = Post::with('user')
            ->where('visibility', 'public')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'posts' => $posts
        ]);
    }

    /**
     * Store a newly created post.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'required|string',
            'visibility' => 'nullable|in:public,private,followers',
            'media' => 'nullable|file|max:10240',
        ]);

        try {
            $post = new Post();
            $post->title = $request->title;
            $post->body = $request->body;
            $post->user_id = auth()->id();
            $post->visibility = $request->visibility ?? 'public';
            $post->share_key = Str::random(10);

            // Handle media upload if provided
            if ($request->hasFile('media')) {
                try {
                    Log::info('Media file received', [
                        'filename' => $request->file('media')->getClientOriginalName(),
                        'mime_type' => $request->file('media')->getMimeType(),
                        'size' => $request->file('media')->getSize()
                    ]);
                    
                    $file = $request->file('media');
                    $mediaLink = $this->fileUploadService->uploadFile($file, 'media/images');
                    
                    if (!$mediaLink) {
                        throw new \Exception('File upload returned empty URL');
                    }
                    
                    $post->media_link = $mediaLink;
                    $post->media_type = $file->getMimeType();
                    $post->original_filename = $file->getClientOriginalName();
                    
                    Log::info('File upload successful', [
                        'media_link' => $mediaLink,
                        'media_type' => $post->media_type,
                        'original_filename' => $post->original_filename
                    ]);
                } catch (\Exception $e) {
                    Log::error('File upload failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $request->file('media')->getClientOriginalName()
                    ]);
                    
                    return response()->json([
                        'message' => 'File upload failed: ' . $e->getMessage()
                    ], 500);
                }
            }

            $post->save();

            // Process mentions if any
            if (Str::contains($post->body, '@')) {
                $post->processMentions($post->body);
            }

            return response()->json([
                'message' => 'Post created successfully',
                'post' => $post
            ], 201);
        } catch (\Exception $e) {
            Log::error('Post creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Post creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified post.
     */
    public function show($id)
    {
        $post = Post::with('user', 'comments.user', 'likes')->findOrFail($id);
        
        // Check visibility permissions
        $user = auth()->user();
        
        if ($post->visibility === 'private' && (!$user || $post->user_id !== $user->id)) {
            return response()->json([
                'message' => 'You do not have permission to view this post'
            ], 403);
        }
        
        if ($post->visibility === 'followers' && (!$user || !$user->isFollowing($post->user_id))) {
            return response()->json([
                'message' => 'This post is only visible to followers'
            ], 403);
        }

        return response()->json([
            'post' => $post
        ]);
    }

    /**
     * Update the specified post.
     */
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        
        // Check if user owns the post
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'You do not have permission to edit this post'
            ], 403);
        }
        
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'visibility' => 'nullable|in:public,private,followers',
            'media' => 'nullable|file|max:10240',
        ]);

        if ($request->has('title')) {
            $post->title = $request->title;
        }
        
        if ($request->has('body')) {
            $post->body = $request->body;
            
            // Process mentions if body was updated
            if (Str::contains($post->body, '@')) {
                $post->processMentions($post->body);
            }
        }
        
        if ($request->has('visibility')) {
            $post->visibility = $request->visibility;
        }
        
        // Handle media update if provided
        if ($request->hasFile('media')) {
            try {
                // Delete old media if it exists
                if ($post->media_link) {
                    $this->fileUploadService->deleteFile($post->media_link);
                }
                
                $file = $request->file('media');
                $mediaLink = $this->fileUploadService->uploadFile($file, 'media/images');
                
                if (!$mediaLink) {
                    throw new \Exception('File upload returned empty URL');
                }
                
                $post->media_link = $mediaLink;
                $post->media_type = $file->getMimeType();
                $post->original_filename = $file->getClientOriginalName();
            } catch (\Exception $e) {
                Log::error('File update failed', [
                    'error' => $e->getMessage(),
                    'post_id' => $post->id
                ]);
                
                return response()->json([
                    'message' => 'File update failed: ' . $e->getMessage()
                ], 500);
            }
        }

        $post->save();

        return response()->json([
            'message' => 'Post updated successfully',
            'post' => $post
        ]);
    }

    /**
     * Remove the specified post.
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        
        // Check if user owns the post
        if ($post->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'You do not have permission to delete this post'
            ], 403);
        }

        // Delete media if it exists
        if ($post->media_link) {
            try {
                $this->fileUploadService->deleteFile($post->media_link);
            } catch (\Exception $e) {
                Log::warning('Failed to delete post media: ' . $e->getMessage());
                // Continue with post deletion even if media deletion fails
            }
        }

        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully'
        ]);
    }

    /**
     * Display a post by its share key (public link)
     */
    public function viewSharedPost($shareKey)
    {
        try {
            // Log the share key access attempt
            Log::info('Attempting to view shared post', ['share_key' => $shareKey]);
            
            // Find the post with the exact share key
            $post = Post::where('share_key', $shareKey)->first();
            
            // Check if post exists
            if (!$post) {
                Log::warning('No post found with share key', ['share_key' => $shareKey]);
                return response()->json([
                    'message' => 'Post not found',
                    'share_key' => $shareKey
                ], 404);
            }
            
            // Only public posts should be shareable
            if ($post->visibility !== 'public') {
                Log::warning('Non-public post access attempted', [
                    'share_key' => $shareKey, 
                    'visibility' => $post->visibility
                ]);
                return response()->json([
                    'message' => 'This post is not available for public viewing'
                ], 403);
            }
            
            // Convert markdown to safe HTML
            $post->body = strip_tags(Str::markdown($post->body), '<p><ul><ol><li><strong><em><h3><br>');
            
            // Detailed logging for successful retrieval
            Log::info('Shared post retrieved successfully', [
                'post_id' => $post->id,
                'share_key' => $shareKey
            ]);
            
            // Include additional context for shared posts
            return response()->json([
                'post' => $post,
                'user' => $post->user ? [
                    'id' => $post->user->id,
                    'username' => $post->user->username,
                    'avatar' => $post->user->avatar
                ] : null,
                'stats' => [
                    'likes' => $post->likes()->count(),
                    'comments' => $post->comments()->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            // Comprehensive error logging
            Log::error('Error retrieving shared post', [
                'share_key' => $shareKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get posts for a specific user
     */
    public function getUserPosts($username)
    {
        $user = \App\Models\User::where('username', $username)->firstOrFail();
        
        $query = Post::where('user_id', $user->id);
        
        // If not the post owner, apply visibility restrictions
        if (!auth()->check() || auth()->id() !== $user->id) {
            $query->where('visibility', 'public');
            
            // Add followers-only posts if authenticated user follows the post author
            if (auth()->check()) {
                $isFollowing = \App\Models\Follow::where('user_id', auth()->id())
                    ->where('followeduser', $user->id)
                    ->exists();
                    
                if ($isFollowing) {
                    $query->orWhere(function($q) use ($user) {
                        $q->where('user_id', $user->id)
                          ->where('visibility', 'followers');
                    });
                }
            }
        }
        
        $posts = $query->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'posts' => $posts
        ]);
    }
}