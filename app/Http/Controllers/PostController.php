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

	public function storeNewPost(Request $request) {
		// Validate incoming request parameters
		$incomingFields = $request->validate([
			'title' => 'required',
			'body' => 'required',
		]);

		// Sanitize user input
		$incomingFields['title'] = strip_tags($incomingFields['title']);
		$incomingFields['body'] = strip_tags($incomingFields['body']);

		// Access user id from the authenticated user
		$incomingFields['user_id'] = auth()->user()->id;

		// Create a new post
		$newPost = Post::create($incomingFields);

		// Return a JSON response
		return response()->json([
			'message' => 'New post created successfully',
			'post' => $newPost,
		], 201);
	}
}