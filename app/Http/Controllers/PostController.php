<?php
// app/Http/Controllers/PostController.php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Like;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\FileUploadService;

class PostController extends Controller {
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Retrieve a single post with its body converted from Markdown to HTML,
     * while only allowing specific HTML tags.
     */
    public function viewSinglePost(Post $post) {
        $post['body'] = strip_tags(Str::markdown($post->body), '<p><ul><ol><li><strong><em><h3><br>');
        return response()->json(['post' => $post]);
    }

    /**
     * Retrieve a post by its share key, accessible without authentication
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
     * Store a new post.
     * For media posts, uses Cloudinary for file uploads for images, videos, and files.
     */
    public function storePost(Request $request) {
        // Validate the incoming request parameters
        $request->validate([
            // Either text content is provided (for text-only posts)
            // or media_type must be provided and be one of the allowed values.
            'text_content' => 'required_without:media_type|string',
            'media_type'   => 'required|string|in:image,video,text,file',
            // For image, video, or file posts, the 'media_file' field is required.
            'media_file'   => 'required_if:media_type,image,video,file|file|max:102400', // 100MB
            // For video posts, a thumbnail is optional
            'media_thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'visibility'   => 'required|in:public,followers',
        ]);

        $newPost = new Post();
        $newPost->user_id = auth()->id(); // Use authenticated user ID for security
        $newPost->media_type = $request->media_type;
        $newPost->visibility = $request->visibility;
        
        // Generate a stable share key
        $newPost->share_key = hash('sha256', 
            $newPost->user_id . 
            now()->timestamp . 
            Str::random(16)
        );
        
        if ($request->media_type === 'text') {
            // For text posts, simply use the provided text content.
            $newPost->body = $request->text_content;
        } else {
            // For media posts, handle the file upload.
            $newPost->body = $request->text_content ?? ''; // Use consistent field name
            
            if ($request->hasFile('media_file')) {
                if ($request->media_type == 'image') {
                    // Validate image
                    $request->validate([
                        'media_file' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                    ]);
                    
                    // Upload image to Cloudinary
                    $newPost->media_link = $this->fileUploadService->uploadFile(
                        $request->file('media_file'),
                        'media/images'
                    );
                    
                } else if ($request->media_type == 'video') {
                    // Validate video
                    $request->validate([
                        'media_file' => 'mimetypes:video/avi,video/mpeg,video/quicktime,video/mp4,video/webm,video/x-matroska|max:102400',
                    ]);
                    
                    // Upload video to Cloudinary
                    $newPost->media_link = $this->fileUploadService->uploadFile(
                        $request->file('media_file'),
                        'media/videos'
                    );
                    
                    // Handle thumbnail if provided (now optional)
                    if ($request->hasFile('media_thumbnail')) {
                        $request->validate([
                            'media_thumbnail' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                        ]);
                        
                        $newPost->media_thumbnail = $this->fileUploadService->uploadFile(
                            $request->file('media_thumbnail'),
                            'media/thumbnails'
                        );
                    }
                } else if ($request->media_type == 'file') {
                    // Allow various file types
                    $newPost->media_link = $this->fileUploadService->uploadFile(
                        $request->file('media_file'),
                        'media/files'
                    );
                    
                    // Store the original filename to display it to users
                    $newPost->original_filename = $request->file('media_file')->getClientOriginalName();
                    
                    // Store mime type for proper rendering in the frontend
                    $newPost->mime_type = $request->file('media_file')->getMimeType();
                }
            }
        }

        // Save the post and process mentions if it contains @username
        if ($newPost->save()) {
            // Process mentions if the post body contains @username
            if (strpos($newPost->body, '@') !== false) {
                $newPost->processMentions($newPost->body);
            }
            
            return response()->json([
                'message' => 'New post created successfully',
                'post' => $newPost,
                'share_url' => $newPost->share_url,
            ], 201); // 201 Created for resource creation
        } else {
            return response()->json(['message' => 'Failed to create post'], 500);
        }
    }
    
    /**
     * Delete a post.
     * Only the owner of the post can delete it.
     */
    public function deletePost(Post $post) {
        // Check if the authenticated user is the owner of the post
        if (auth()->id() !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Delete associated media files from Cloudinary if they exist
        if ($post->media_link) {
            $this->fileUploadService->deleteFile($post->media_link);
        }
        
        if ($post->media_thumbnail) {
            $this->fileUploadService->deleteFile($post->media_thumbnail);
        }
        
        // Delete the post
        if ($post->delete()) {
            return response()->json(['message' => 'Post deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete post'], 500);
        }
    }
    
    /**
     * Get posts for a user.
     */
    public function getUserPosts(Request $request, $userId = null) {
        $userId = $userId ?? auth()->id();
        
        $posts = Post::where('user_id', $userId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return response()->json(['posts' => $posts]);
    }
    
    /**
     * Get statistics (like count and comment count) for a specific post.
     */
    public function getPostStats($postId) {
        $post = Post::findOrFail($postId);
        $likeCount = Like::where('post_id', $postId)->count();
        $commentCount = Comment::where('post_id', $postId)->count();
        
        return response()->json([
            'post_id' => $postId,
            'like_count' => $likeCount,
            'comment_count' => $commentCount
        ]);
    }
}