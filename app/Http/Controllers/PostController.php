<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
     * Store a newly created post with comprehensive media support.
     */
    public function store(Request $request)
    {
        // Validate the incoming request parameters
        $request->validate([
            // Either text content is provided or media_type must be specified
            'text_content' => 'required_without:media_type|string',
            'media_type'   => 'required_with:media_file|string|in:image,video,text,file',
            // For media posts, the file is required based on media type
            'media_file'   => 'required_if:media_type,image,video,file|file|max:10240', // 10MB
            // For video posts, a thumbnail is optional
            'media_thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'visibility'   => 'nullable|in:public,private,followers',
            'title'        => 'nullable|string|max:255'
        ]);

        try {
            $newPost = new Post();
            $newPost->user_id = auth()->id();
            $newPost->title = $request->title;
            
            // Determine media type and content
            $newPost->media_type = $request->media_type ?? 'text';
            $newPost->visibility = $request->visibility ?? 'public';
            $newPost->share_key = Str::random(10);

            // Handle different post types
            if ($newPost->media_type === 'text') {
                // Text-only post
                $newPost->body = $request->text_content;
            } else {
                // Media post
                $newPost->body = $request->text_content ?? '';
                
                if ($request->hasFile('media_file')) {
                    $file = $request->file('media_file');
                    
                    // Specific validations for different media types
                    switch ($newPost->media_type) {
                        case 'image':
                            $request->validate([
                                'media_file' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                            ]);
                            break;
                        
                        case 'video':
                            $request->validate([
                                'media_file' => 'mimetypes:video/avi,video/mpeg,video/quicktime,video/mp4,video/webm,video/x-matroska|max:10240',
                            ]);
                            
                            // Handle optional video thumbnail
                            if ($request->hasFile('media_thumbnail')) {
                                $thumbnailLink = $this->fileUploadService->uploadFile(
                                    $request->file('media_thumbnail'),
                                    'media/thumbnails'
                                );
                                $newPost->media_thumbnail = $thumbnailLink;
                            }
                            break;
                        
                        case 'file':
                            // Allow various file types
                            break;
                    }
                    
                    // Upload the main media file
                    $mediaLink = $this->fileUploadService->uploadFile(
                        $file,
                        'media/' . $newPost->media_type . 's'
                    );
                    
                    // Log file upload details
                    Log::info('File upload details', [
                        'filename' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'media_link' => $mediaLink
                    ]);
                    
                    $newPost->media_link = $mediaLink;
                    $newPost->media_type = $file->getMimeType();
                    $newPost->original_filename = $file->getClientOriginalName();
                }
            }

            // Save the post
            $newPost->save();

            // Process mentions if the post body contains @username
            if (Str::contains($newPost->body, '@')) {
                $newPost->processMentions($newPost->body);
            }

            return response()->json([
                'message' => 'Post created successfully',
                'post' => $newPost,
                'share_url' => route('shared.post', ['shareKey' => $newPost->share_key])
            ], 201);

        } catch (\Exception $e) {
            // Comprehensive error logging
            Log::error('Post creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
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

    /**
     * Get statistics (like count and comment count) for a specific post.
     */
    public function getPostStats($postId)
    {
        $post = Post::findOrFail($postId);
        $likeCount = \App\Models\Like::where('post_id', $postId)->count();
        $commentCount = \App\Models\Comment::where('post_id', $postId)->count();
        
        return response()->json([
            'post_id' => $postId,
            'like_count' => $likeCount,
            'comment_count' => $commentCount
        ]);
    }

    /**
     * Regenerate the share key for a post.
     * Only the post owner can regenerate the share key.
     */
    public function regenerateShareKey(Post $post)
    {
        // Check if the authenticated user is the owner of the post
        if (auth()->id() !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $post->share_key = Str::random(10);
        
        if ($post->save()) {
            return response()->json([
                'message' => 'Share key regenerated successfully',
                'share_url' => route('shared.post', ['shareKey' => $post->share_key])
            ]);
        } else {
            return response()->json(['message' => 'Failed to regenerate share key'], 500);
        }
    }

    /**
     * Backfill share keys for existing posts that don't have them.
     * This method is intended for administrative use.
     */
    public function backfillShareKeys(Request $request)
    {
        // Check if user has admin privileges
        if (!$request->user()->isAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Find posts without share keys
        $posts = Post::whereNull('share_key')->get();
        $count = $posts->count();
        
        if ($count === 0) {
            return response()->json(['message' => 'No posts found without share keys']);
        }
        
        $processed = 0;
        $errors = 0;
        
        // Generate and save share keys
        foreach ($posts as $post) {
            DB::beginTransaction();
            try {
                $post->share_key = Str::random(10);
                $post->save();
                DB::commit();
                $processed++;
            } catch (\Exception $e) {
                DB::rollBack();
                $errors++;
                
                // Log the error for admin review
                Log::error('Share key generation failed', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return response()->json([
            'message' => 'Share keys have been generated for posts',
            'processed' => $processed,
            'errors' => $errors,
            'total' => $count
        ]);
    }
}