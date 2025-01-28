<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller {
	//
	public function viewSinglePost(Post $post) {
		$post['body'] = strip_tags(Str::markdown($post->body), '<p><ul><ol><li><strong><em><h3><br>');
		return response()->json(['post' => $post]);
	}

	public function storePost(Request $request) {
        // Validate incoming request parameters
        $request->validate([
            'text_content' => 'required_without:media_type|string',
            'media_type' => 'required|string|in:image,video,text',
            'media_thumbnail' => 'required_if:media_type,video|image|mimes:jpg,jpeg,png,gif|max:5120',
            'media_link' => 'required_if:media_type,image|mimetypes:image/jpeg,image/png,image/gif|max:5120',
            'visibility' => 'required|in:public,followers',
        ]);

        $newPost = new Post();

        if ($request->media_type === 'text') {
            $newPost->body = $request->text_content;
        } else {
            if ($request->hasFile('media_link')) {
                if ($request->media_type == 'image') {
                    $request->validate([
                        'media_link' => 'image|mimes:jpg,jpeg,png,gif|max:5120',
                        'text_content' =>  'nullable|string|max:1000'
                    ]);
                } else if ($request->media_type == 'video') {
                    $request->validate([
                        'media_link' => 'mimetypes:video/avi,video/mpeg,video/quicktime|max:5120',
                        'text_content' =>  'nullable|string|max:1000'
                    ]);

                    if ($request->file('media_thumbnail')) {
                        $request->validate([
                            'media_thumbnail' => 'image|mimes:jpg,jpeg,png,gif|max:5120',
                            'text_content' =>  'nullable|string|max:1000'
                        ]);

                        $newPost->media_thumbnail = $request->file('media_thumbnail')->store('media_thumbnails');
                    }
                }

                $newPost->media_link = $request->file('media_link')->store('media_files');
            }

            $newPost->body = $request->body;
        }

        $newPost->user_id = $request->user_id;
        $newPost->media_type = $request->media_type;
        $newPost->visibility = $request->visibility;

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
