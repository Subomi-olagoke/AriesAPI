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
        
        // Check if this is the first comment on the post
        $isFirstComment = Comment::where('post_id', $post_id)->count() === 0;
        $comment->is_first = $isFirstComment;

        try {
            $comment->save();
            $post = Post::find($comment->post_id);

            if ($post) {
                $notifiable = $post->user;
                $notifiable->notify(new CommentNotification($comment, $user));
            }

            // Reload the comment to get fresh data including relationships
            $comment = Comment::find($comment->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
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
        $user = auth()->user();
        $comment = Comment::find($commentId);
        
        if (!$comment) {
            return response()->json([
                'message' => 'Comment not found'
            ], 404);
        }
        
        // Check if user owns the comment or owns the post (post owner can delete any comment)
        $isCommentOwner = $comment->user_id === $user->id;
        $isPostOwner = $comment->post->user_id === $user->id;
        
        if (!$isCommentOwner && !$isPostOwner) {
            return response()->json([
                'message' => 'You do not have permission to delete this comment'
            ], 403);
        }
        
        $deleted = $comment->delete();

        if($deleted) {
            return response()->json([
                'message' => 'Comment deleted successfully'
            ], 200);
        }
            
        return response()->json([
            'message' => 'Error deleting comment'
        ], 500);
    }

    public function displayComments(Post $post) {
        $comments = Comment::where('post_id', $post->id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        if($comments->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No comments for this post yet',
                'comments' => []
            ], 200); // Return 200 with empty array instead of 404
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Comments retrieved successfully',
            'comments' => $comments
        ], 200);
    }
    
    public function getCommentCount($postId) {
        $count = Comment::where('post_id', $postId)->count();
        return response()->json([
            'success' => true,
            'post_id' => $postId,
            'comment_count' => $count
        ]);
    }
}