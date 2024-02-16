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

	public function storePost(Request $request, Post $post) {
		// Validate incoming request parameters
		$request->validate([
			'media_type' => 'required|string|in:image,video',
			'media_thumbnail' => 'required_if:media_type,video',
			'visibility' => 'required|in:public,followers',
		]);
		$newPost = new Post();

		if ($request->hasFile('media_link')) {
			if ($request->media_type == 'image') {
				$request->validate([
					'media_link' => 'image|mimes:jpg,jpeg,png,gif|max:5120',
				]);
			} else {
				$request->validate([
					'media_link' => 'mimetypes:video/avi,video/mpeg,video/quicktime|max:5120']);

				if ($request->file('media_thumbnail')) {
					$request->validate([
						'media_thumbnail' => 'image|mimes:jpg,jpeg,png,gif|max:5120',
					]);

					$newPost->media_thumbnail = $request->file('media_thumbnail')->store('media_link');

				}

			}
			$newPost->media_link = $request->file('media_link')->store('media_link');
		}

		$newPost->user_id = $request->user_id;
        $newPost->media_type = $request->media_type;
		$newPost->visibility = $request->visibility;
		$newPost->body = $request->body;

		if ($newPost->save()) {
		 return response()->json([
                'message' => 'New post created successfully',
                'post' => $newPost,
            ], 200);
		} else {
			return response()->json(['message' => 'some error occurred, please try again',
			], 500);
		}


	}
}
