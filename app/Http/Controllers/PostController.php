<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * For media posts, the server now handles file uploads for images and videos
     * using the 'media_file' field.
     */
    public function storePost(Request $request) {
        // Validate the incoming request parameters
        $request->validate([
            // Either text content is provided (for text-only posts)
            // or media_type must be provided and be one of the allowed values.
            'text_content' => 'required_without:media_type|string',
            'media_type'   => 'required|string|in:image,video,text',
            // For image or video posts, the 'media_file' field is required.
            'media_file'   => 'required_if:media_type,image,video|file|max:5120',
            // For video posts, a thumbnail is also required.
            'media_thumbnail' => 'required_if:media_type,video|image|mimes:jpg,jpeg,png,gif|max:5120',
            'visibility'   => 'required|in:public,followers',
        ]);

        $newPost = new Post();

        if ($request->media_type === 'text') {
            // For text posts, simply use the provided text content.
            $newPost->body = $request->text_content;
        } else {
            // For media posts, handle the file upload.
            if ($request->hasFile('media_file')) {
                if ($request->media_type == 'image') {
                    // Validate image file (if not already validated)
                    $request->validate([
                        'media_file'   => 'image|mimes:jpg,jpeg,png,gif|max:5120',
                        'text_content' => 'nullable|string|max:1000'
                    ]);
                } else if ($request->media_type == 'video') {
                    // Validate video file (if not already validated)
                    $request->validate([
                        'media_file'   => 'mimetypes:video/avi,video/mpeg,video/quicktime|max:5120',
                        'text_content' => 'nullable|string|max:1000'
                    ]);
                    
                    // If a thumbnail is provided, validate and store it.
                    if ($request->file('media_thumbnail')) {
                        $request->validate([
                            'media_thumbnail' => 'image|mimes:jpg,jpeg,png,gif|max:5120',
                            'text_content'    => 'nullable|string|max:1000'
                        ]);
                        $newPost->media_thumbnail = $request->file('media_thumbnail')->store('media_thumbnails');
                    }
                }

                // Store the uploaded media file and save the file path.
                $newPost->media_link = $request->file('media_file')->store('media_files');
            }

            // Optionally, set a caption or description for the media post.
            $newPost->body = $request->body;
        }

        // Assign additional fields
        $newPost->user_id = $request->user_id;
        $newPost->media_type = $request->media_type;
        $newPost->visibility = $request->visibility;

        // Save the post and return a response.
        if ($newPost->save()) {
            return response()->json([
                'message' => 'New post created successfully',
                'post' => $newPost,
            ], 200);
        } else {
            return response()->json(['message' => 'Some error occurred, please try again'], 500);
        }
    }
}