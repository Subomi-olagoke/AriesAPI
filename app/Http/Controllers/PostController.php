<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
     * Display a listing of posts with their media.
     */
    public function index()
    {
        $posts = Post::with(['user', 'media'])
            ->where('visibility', 'public')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Add statistics to each post
        $posts->getCollection()->transform(function ($post) {
            $post->like_count = \App\Models\Like::where('post_id', $post->id)->count();
            $post->comment_count = \App\Models\Comment::where('post_id', $post->id)->count();
            $post->selection_count = $post->readlistItems()->count();
            
            // Add user-specific fields (is_liked, is_in_readlist)
            if (auth()->check()) {
                $userId = auth()->id();
                
                // Check if user has liked this post
                if (Schema::hasColumn('likes', 'likeable_type')) {
                    $post->is_liked = $post->likes()->where('user_id', $userId)->exists();
                } else {
                    $post->is_liked = $post->likes()->where('user_id', $userId)->exists();
                }
                
                // Check if user has added this post to any readlist
                $post->is_in_readlist = $post->readlistItems()
                    ->whereHas('readlist', function ($query) use ($userId) {
                        $query->where('user_id', $userId);
                    })
                    ->exists();
            } else {
                $post->is_liked = false;
                $post->is_in_readlist = false;
            }
            
            return $post;
        });

        return response()->json([
            'posts' => $posts
        ]);
    }

    /**
     * Store a newly created post with support for multiple media files.
     */
    public function store(Request $request)
    {
        // Validate the incoming request parameters
        // Remove subscription-based limits - allow all users to upload media
        $maxVideoSize = 500 * 1024 * 1024; // 500MB for all users
        $maxImageSize = 50 * 1024 * 1024;  // 50MB for all users
        
        // Validate the incoming request parameters
        $request->validate([
            // Text content is now completely optional
            'text_content' => 'nullable|string',
            // Media files array
            'media_files'  => 'nullable|array',
            'media_files.*' => [
                'file',
                function ($attribute, $value, $fail) use ($maxVideoSize, $maxImageSize) {
                    $mime = $value->getMimeType();
                    $size = $value->getSize();
                    
                    if (Str::startsWith($mime, 'video/')) {
                        if ($size > $maxVideoSize) {
                            $fail('Video file size exceeds limit of ' . 
                                  round($maxVideoSize / (1024 * 1024), 1) . 'MB.');
                        }
                    } elseif (Str::startsWith($mime, 'image/')) {
                        if ($size > $maxImageSize) {
                            $fail('Image file size exceeds limit of ' . 
                                  round($maxImageSize / (1024 * 1024), 1) . 'MB.');
                        }
                    } else {
                        // For other file types, use the lower of the two limits
                        $maxSize = min($maxVideoSize, $maxImageSize);
                        if ($size > $maxSize) {
                            $fail('File size exceeds limit of ' . 
                                  round($maxSize / (1024 * 1024), 1) . 'MB.');
                        }
                    }
                }
            ],
            'visibility'   => 'nullable|in:public,private,followers',
            'title'        => 'nullable|string|max:255',
            // Optional AI analysis request - remove premium restriction
            'analyze_with_ai' => 'nullable|boolean'
        ]);

        // Remove AI analysis premium check - allow all users to analyze posts
        // if ($request->has('analyze_with_ai') && $request->analyze_with_ai && !$user->canAnalyzePosts()) {
        //     return response()->json([
        //         'message' => 'AI post analysis is a premium feature. Please upgrade your subscription to use this feature.',
        //         'premium_required' => true
        //     ], 403);
        // }
        
        try {
            DB::beginTransaction();
            
            $newPost = new Post();
            $newPost->user_id = auth()->id();
            $newPost->title = $request->title;
            $newPost->visibility = $request->visibility ?? 'public';
            $newPost->share_key = Str::random(10);
            $newPost->body = $request->text_content ?? '';
            
            // Set default media type to text (will be updated if media is attached)
            $newPost->media_type = 'text';
            
            // Check if we have multiple media files
            $hasMultipleFiles = $request->hasFile('media_files') && count($request->file('media_files')) > 1;
            $newPost->has_multiple_media = $hasMultipleFiles;
            
            // For single file backward compatibility
            if ($request->hasFile('media_file')) {
                $file = $request->file('media_file');
                
                // Upload the main media file
                $mediaLink = $this->fileUploadService->uploadFile(
                    $file,
                    'media/singles'
                );
                
                // Set directly on post for backward compatibility
                $newPost->media_link = $mediaLink;
                $newPost->media_type = $file->getMimeType();
                $newPost->original_filename = $file->getClientOriginalName();
            }
            
            // Save the post first to get an ID
            $newPost->save();
            
            // Process media files (if any)
            $mediaFiles = [];
            
            if ($request->hasFile('media_files')) {
                $files = $request->file('media_files');
                $order = 0;
                
                foreach ($files as $file) {
                    // Determine the media folder based on mime type
                    $mimeType = $file->getMimeType();
                    $mediaFolder = 'media/';
                    
                    if (Str::startsWith($mimeType, 'image/')) {
                        $mediaFolder .= 'images';
                    } elseif (Str::startsWith($mimeType, 'video/')) {
                        $mediaFolder .= 'videos';
                    } elseif (Str::startsWith($mimeType, 'audio/')) {
                        $mediaFolder .= 'audios';
                    } else {
                        $mediaFolder .= 'files';
                    }
                    
                    // Upload the file
                    $mediaLink = $this->fileUploadService->uploadFile($file, $mediaFolder);
                    
                    // Create media record
                    $media = new PostMedia([
                        'post_id' => $newPost->id,
                        'media_link' => $mediaLink,
                        'media_type' => $mimeType,
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => $mimeType,
                        'order' => $order++
                    ]);
                    
                    $media->save();
                    $mediaFiles[] = $media;
                    
                    // Log file upload details
                    Log::info('File upload details', [
                        'filename' => $file->getClientOriginalName(),
                        'mime_type' => $mimeType,
                        'size' => $file->getSize(),
                        'media_link' => $mediaLink
                    ]);
                }
                
                // If we have at least one media file and no single file was uploaded
                // set the post's primary media info to the first file for backwards compatibility
                if (count($mediaFiles) > 0 && !$request->hasFile('media_file')) {
                    $firstMedia = $mediaFiles[0];
                    $newPost->media_link = $firstMedia->media_link;
                    $newPost->media_type = $firstMedia->media_type;
                    $newPost->original_filename = $firstMedia->original_filename;
                    $newPost->mime_type = $firstMedia->mime_type;
                    $newPost->save();
                }
            }

            // Process mentions if the post body contains @username
            if (Str::contains($newPost->body, '@')) {
                $newPost->processMentions($newPost->body);
            }
            
            DB::commit();
            
            // Load the media relationship for the response
            $newPost->load('media');
            
            // If AI analysis was requested - allow all users now
            $aiAnalysis = null;
            if ($request->has('analyze_with_ai') && $request->analyze_with_ai) {
                try {
                    $aiAnalysis = $this->analyzePostWithAI($newPost);
                } catch (\Exception $e) {
                    Log::error('AI analysis failed for post ' . $newPost->id . ': ' . $e->getMessage());
                    // Don't fail the post creation if AI analysis fails
                }
            }
            
            return response()->json([
                'message' => 'Post created successfully',
                'post' => $newPost,
                'ai_analysis' => $aiAnalysis
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Post creation failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to create post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified post.
     */
    public function show($id)
    {
        $post = Post::with('user', 'comments.user', 'likes', 'media')->findOrFail($id);
        
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
     * Display the specified post using route model binding.
     * This is used by the viewSinglePost route.
     */
    public function viewSinglePost(Post $post)
    {
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
        
        // Load the relationships
        $post->load('user', 'comments.user', 'likes', 'media');
        
        // The iOS app is expecting a 'data' wrapper in the response
        return response()->json([
            'data' => $post
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
        
        // Remove subscription-based limits - allow all users to upload media
        $maxVideoSize = 500 * 1024 * 1024; // 500MB for all users
        $maxImageSize = 50 * 1024 * 1024;  // 50MB for all users
        
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'visibility' => 'nullable|in:public,private,followers',
            'media' => [
                'nullable',
                'file',
                function ($attribute, $value, $fail) use ($maxVideoSize, $maxImageSize) {
                    if (!$value) return;
                    
                    $mime = $value->getMimeType();
                    $size = $value->getSize();
                    
                    if (Str::startsWith($mime, 'video/')) {
                        if ($size > $maxVideoSize) {
                            $fail('Video file size exceeds limit of ' . 
                                  round($maxVideoSize / (1024 * 1024), 1) . 'MB.');
                        }
                    } elseif (Str::startsWith($mime, 'image/')) {
                        if ($size > $maxImageSize) {
                            $fail('Image file size exceeds limit of ' . 
                                  round($maxImageSize / (1024 * 1024), 1) . 'MB.');
                        }
                    } else {
                        // For other file types, use the lower of the two limits
                        $maxSize = min($maxVideoSize, $maxImageSize);
                        if ($size > $maxSize) {
                            $fail('File size exceeds limit of ' . 
                                  round($maxSize / (1024 * 1024), 1) . 'MB.');
                        }
                    }
                }
            ],
            'media_files' => 'nullable|array',
            'media_files.*' => [
                'file',
                function ($attribute, $value, $fail) use ($maxVideoSize, $maxImageSize) {
                    $mime = $value->getMimeType();
                    $size = $value->getSize();
                    
                    if (Str::startsWith($mime, 'video/')) {
                        if ($size > $maxVideoSize) {
                            $fail('Video file size exceeds limit of ' . 
                                  round($maxVideoSize / (1024 * 1024), 1) . 'MB.');
                        }
                    } elseif (Str::startsWith($mime, 'image/')) {
                        if ($size > $maxImageSize) {
                            $fail('Image file size exceeds limit of ' . 
                                  round($maxImageSize / (1024 * 1024), 1) . 'MB.');
                        }
                    } else {
                        // For other file types, use the lower of the two limits
                        $maxSize = min($maxVideoSize, $maxImageSize);
                        if ($size > $maxSize) {
                            $fail('File size exceeds limit of ' . 
                                  round($maxSize / (1024 * 1024), 1) . 'MB.');
                        }
                    }
                }
            ],
            'delete_media_ids' => 'nullable|array', // IDs of media to delete
            'delete_media_ids.*' => 'numeric|exists:post_media,id',
            'analyze_with_ai' => 'nullable|boolean'
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
        
        try {
            DB::beginTransaction();
            
            // Handle single media update if provided (backward compatibility)
            if ($request->hasFile('media')) {
                // Delete old media if it exists
                if ($post->media_link) {
                    $this->fileUploadService->deleteFile($post->media_link);
                }
                
                $file = $request->file('media');
                $mediaLink = $this->fileUploadService->uploadFile($file, 'media/singles');
                
                if (!$mediaLink) {
                    throw new \Exception('File upload returned empty URL');
                }
                
                $post->media_link = $mediaLink;
                $post->media_type = $file->getMimeType();
                $post->original_filename = $file->getClientOriginalName();
                $post->mime_type = $file->getMimeType();
            }
            
            // Handle multiple media files if provided
            if ($request->hasFile('media_files')) {
                // Set the post to have multiple media
                $post->has_multiple_media = true;
                
                $files = $request->file('media_files');
                $lastOrder = $post->media()->max('order') ?? -1;
                $order = $lastOrder + 1;
                
                foreach ($files as $file) {
                    // Determine the media folder based on mime type
                    $mimeType = $file->getMimeType();
                    $mediaFolder = 'media/';
                    
                    if (Str::startsWith($mimeType, 'image/')) {
                        $mediaFolder .= 'images';
                    } elseif (Str::startsWith($mimeType, 'video/')) {
                        $mediaFolder .= 'videos';
                    } elseif (Str::startsWith($mimeType, 'audio/')) {
                        $mediaFolder .= 'audios';
                    } else {
                        $mediaFolder .= 'files';
                    }
                    
                    // Upload the file
                    $mediaLink = $this->fileUploadService->uploadFile($file, $mediaFolder);
                    
                    // Create media record
                    $media = new PostMedia([
                        'post_id' => $post->id,
                        'media_link' => $mediaLink,
                        'media_type' => $mimeType,
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type' => $mimeType,
                        'order' => $order++
                    ]);
                    
                    $media->save();
                    
                    // Log file upload details
                    Log::info('File upload details', [
                        'filename' => $file->getClientOriginalName(),
                        'mime_type' => $mimeType,
                        'size' => $file->getSize(),
                        'media_link' => $mediaLink
                    ]);
                }
            }
            
            // Handle media deletions if requested
            if ($request->has('delete_media_ids') && is_array($request->delete_media_ids)) {
                foreach ($request->delete_media_ids as $mediaId) {
                    $media = PostMedia::where('id', $mediaId)
                        ->where('post_id', $post->id)
                        ->first();
                        
                    if ($media) {
                        // Delete the file from storage
                        $this->fileUploadService->deleteFile($media->media_link);
                        
                        // Delete the record
                        $media->delete();
                    }
                }
                
                // If we deleted all media, update has_multiple_media flag
                if ($post->media()->count() <= 1) {
                    $post->has_multiple_media = false;
                }
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Media update failed', [
                'error' => $e->getMessage(),
                'post_id' => $post->id
            ]);
            
            return response()->json([
                'message' => 'Media update failed: ' . $e->getMessage()
            ], 500);
        }

        $post->save();

        // Load the media relationship for the response
        $post->load('media');
        
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

        // Delete all media files associated with this post
        try {
            // First delete the main media if it exists (for backward compatibility)
            if ($post->media_link) {
                $this->fileUploadService->deleteFile($post->media_link);
            }
            
            // Delete all media files in the PostMedia table
            $mediaFiles = $post->media()->get();
            foreach ($mediaFiles as $media) {
                $this->fileUploadService->deleteFile($media->media_link);
                $media->delete();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete some post media: ' . $e->getMessage());
            // Continue with post deletion even if media deletion fails
        }

        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully'
        ]);
    }

    /**
     * Delete a post (alias for destroy method)
     */
    public function deletePost($id)
    {
        return $this->destroy($id);
    }

    /**
     * Display a post by its share key (public link)
     */
    public function viewSharedPost($shareKey)
    {
        try {
            // Log the share key access attempt
            Log::info('Attempting to view shared post', ['share_key' => $shareKey]);
            
            // Find the post with the exact share key and load media
            $post = Post::with('media')->where('share_key', $shareKey)->first();
            
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
        
        $posts = $query->with(['user', 'media'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'posts' => $posts
        ]);
    }

    /**
     * Get statistics (like count, comment count, and selection count) for a specific post.
     */
    public function getPostStats($postId)
    {
        $post = Post::findOrFail($postId);
        $likeCount = \App\Models\Like::where('post_id', $postId)->count();
        $commentCount = \App\Models\Comment::where('post_id', $postId)->count();
        $selectionCount = $post->readlistItems()->count();
        
        return response()->json([
            'post_id' => $postId,
            'like_count' => $likeCount,
            'comment_count' => $commentCount,
            'selection_count' => $selectionCount
        ]);
    }
    
    /**
     * Get the number of readlists that include this post (bookmark count)
     */
    public function getSelectionCount($postId)
    {
        try {
            $post = Post::findOrFail($postId);
            $bookmarkCount = $post->readlistItems()->count();
            
            return response()->json([
                'post_id' => (int)$postId, // Cast to integer
                'bookmark_count' => $bookmarkCount // Renamed from selection_count
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Post not found',
                'post_id' => (int)$postId, // Cast to integer
                'bookmark_count' => 0 // Renamed from selection_count
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving bookmark count: ' . $e->getMessage(),
                'post_id' => (int)$postId, // Cast to integer
                'bookmark_count' => 0 // Renamed from selection_count
            ], 500);
        }
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