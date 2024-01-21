<?php

namespace App\Http\Controllers;

use App\Models\comments;
use App\Models\User;
use Illuminate\Http\Request;

class CommentController extends Controller {
	public function postComment(Request $request) {
		$request->validate([
			'post_id' => 'required',
			'content' => 'required|string|max:255',
		]);

		//checking if user is authenticated
		$user = $request->user();
		// if (!$user) {
		// 	return response()->json([
		// 		'message' => 'User is not authenticated',
		// 	],
		// 		401);
		// }

		$comment = new comments();
		$comment->post_id = $request->post_id;
		$comment->user_id = $user->id;
		$comment->content = $request->content;

		if ($comment->save()) {
			return response()->json([
				'message' => 'Comment successful',
				'comment' => $comment,
			],
				201);
		} else {
			return response()->json([
				'message' => "Some error occurred, please try again",
			],
				500);

		}

	}
}
