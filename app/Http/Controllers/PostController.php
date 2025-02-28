<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

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
     * For media posts, the server handles file uploads for images and videos.
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
                if ($request->media_type == 'image') {
                    // Validate image file with extended mime types
                    $request->validate([
                        'media_file' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                    ]);
                    
                    // Store with appropriate path and disk
                    $newPost->media_link = $request->file('media_file')->store('media/images', 'public');
                } else if ($request->media_type == 'video') {
                    // Validate video file with more format support
                    $request->validate([
                        'media_file' => 'mimetypes:video/avi,video/mpeg,video/quicktime,video/mp4,video/webm,video/x-matroska|max:10240',
                    ]);
                    
                    // Store video in public disk with appropriate path
                    $newPost->media_link = $request->file('media_file')->store('media/videos', 'public');
                    
                    // If a thumbnail is provided, validate and store it.
                    if ($request->hasFile('media_thumbnail')) {
                        $request->validate([
                            'media_thumbnail' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                        ]);
                        $newPost->media_thumbnail = $request->file('media_thumbnail')->store('media/thumbnails', 'public');
                    }
                }
            }
        }

        // Save the post and return a response.
        if ($newPost->save()) {
            // Add full URLs to media files
            if ($newPost->media_link) {
                $newPost->media_link = Storage::url($newPost->media_link);
            }
            if ($newPost->media_thumbnail) {
                $newPost->media_thumbnail = Storage::url($newPost->media_thumbnail);
            }
            
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
        
        // Delete associated media files if they exist
        if ($post->media_link) {
            $mediaPath = str_replace('/storage/', 'public/', $post->media_link);
            if (Storage::exists($mediaPath)) {
                Storage::delete($mediaPath);
            }
        }
        
        if ($post->media_thumbnail) {
            $thumbnailPath = str_replace('/storage/', 'public/', $post->media_thumbnail);
            if (Storage::exists($thumbnailPath)) {
                Storage::delete($thumbnailPath);
            }
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
}