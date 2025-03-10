<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Like;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Services\FileUploadService;

class PostController extends Controller {
    /**
     * Retrieve a single post with its body converted from Markdown to HTML,
     * while only allowing specific HTML tags.
     */
    public function viewSinglePost(Post $post) {
        $post['body'] = strip_tags(Str::markdown($post->body), '<p><ul><ol><li><strong><em><h3><br>');
        return response()->json(['post' => $post]);
    }

    /**
     * Store a new post.
     * For media posts, uses Cloudinary for file uploads for images and videos.
     */
    public function storePost(Request $request) {
        // Validate the incoming request parameters
        $request->validate([
            // Either text content is provided (for text-only posts)
            // or media_type must be provided and be one of the allowed values.
            'text_content' => 'required_without:media_type|string',
            'media_type'   => 'required|string|in:image,video,text',
            // For image or video posts, the 'media_file' field is required.
            'media_file'   => 'required_if:media_type,image,video|file|max:10240', // 10MB
            // For video posts, a thumbnail is also required.
            'media_thumbnail' => 'required_if:media_type,video|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'visibility'   => 'required|in:public,followers',
        ]);

        $newPost = new Post();
        $newPost->user_id = auth()->id(); // Use authenticated user ID for security
        $newPost->media_type = $request->media_type;
        $newPost->visibility = $request->visibility;

        if ($request->media_type === 'text') {
            // For text posts, simply use the provided text content.
            $newPost->body = $request->text_content;
        } else {
            // For media posts, handle the file upload.
            $newPost->body = $request->text_content; // Use consistent field name
            
            if ($request->hasFile('media_file')) {
                $fileUploadService = app(FileUploadService::class);
                
                if ($request->media_type == 'image') {
                    // Validate image
                    $request->validate([
                        'media_file' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                    ]);
                    
                    // Upload image to Cloudinary
                    $newPost->media_link = $fileUploadService->uploadFile(
                        $request->file('media_file'),
                        'media/images'
                    );
                    
                } else if ($request->media_type == 'video') {
                    // Validate video
                    $request->validate([
                        'media_file' => 'mimetypes:video/avi,video/mpeg,video/quicktime,video/mp4,video/webm,video/x-matroska|max:10240',
                    ]);
                    
                    // Upload video to Cloudinary
                    $newPost->media_link = $fileUploadService->uploadFile(
                        $request->file('media_file'),
                        'media/videos'
                    );
                    
                    // Handle thumbnail if provided
                    if ($request->hasFile('media_thumbnail')) {
                        $request->validate([
                            'media_thumbnail' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                        ]);
                        
                        $newPost->media_thumbnail = $fileUploadService->uploadFile(
                            $request->file('media_thumbnail'),
                            'media/thumbnails',
                            [
                                'process_image' => true,
                                'width' => 500,
                                'height' => 300,
                                'fit' => true
                            ]
                        );
                    }
                }
            }
        }

        // Save the post and return a response.
        if ($newPost->save()) {
            return response()->json([
                'message' => 'New post created successfully',
                'post' => $newPost,
            ], 201); // 201 Created for resource creation
        } else {
            return response()->json(['message' => 'Failed to create post'], 500);
        }
    }
    
    /**
     * Delete a post.
     */
    public function deletePost(Post $post) {
        // Check if the authenticated user is the owner of the post
        if (auth()->id() !== $post->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Delete associated media files from Cloudinary if they exist
        $fileUploadService = app(FileUploadService::class);
        
        if ($post->media_link) {
            $fileUploadService->deleteFile($post->media_link);
        }
        
        if ($post->media_thumbnail) {
            $fileUploadService->deleteFile($post->media_thumbnail);
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