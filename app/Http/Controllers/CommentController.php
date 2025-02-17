<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Http\Request;
use App\Notifications\CommentNotification;

class CommentController extends Controller {
	public function postComment(Request $request, $post_id) {
        $request->validate([
            'content' => 'required|string|max:255',
        ]);

        $user = auth()->user();

        $comment = new Comment();
        $comment->post_id = $post_id;
        $comment->user_id = $user->id;
        $comment->content = $request->content;

        try {
            $comment->save();
            $post = Post::find($comment->post_id);

            if ($post) {
                $notifiable = $post->user;
                $notifiable->notify(new CommentNotification($comment, $user));
            }

            return response()->json([
                'message' => 'Comment successful',
                'comment' => $comment,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Some error occurred, please try again',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteComment($commentId) {
        $deleted = Comment::where(['user_id', '=', auth()->user()->id],
        ['id', '=', $commentId])->delete();

        if($deleted) {
            return response()->json([
                'message' => 'comment deleted'
            ], 204);
        }
            return response()->json([
                'message' => 'error deleting comment'
            ], 500);
    }

    public function displayComments(Post $post) {
        //$comments = Comment::where('post_id', '=', $post->id)->get();
        $comments = Comment::where('post_id', $post->id)
        ->with('user')
        ->get();
        if($comments->isEmpty()) {
            return response()->json([
                'message' => 'no comments for this post yet'
            ], 404);
        }
        return response()->json(
            $comments, 200
        );
    }
}
