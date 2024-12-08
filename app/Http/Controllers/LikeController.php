<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Models\Likes;
use App\Models\Comment;
use App\Models\comments;
use Illuminate\Http\Request;

class LikeController {

    public function createLike(Request $request, Post $post = null, Comment $comment = null, Course $course = null) {
        $user = $request->user();

        $postId = $post?->id;
        $commentId = $comment?->id;
        $courseId = $course?->id;

        // Check if the like already exists
        $statCheck = Like::where('user_id', '=', $user->id)
            ->where(function ($query) use ($postId, $commentId, $courseId) {
                $query->when($postId, fn($q) => $q->where('post_id', '=', $postId))
                      ->when($commentId, fn($q) => $q->orWhere('comment_id', '=', $commentId))
                      ->when($courseId, fn($q) => $q->orWhere('course_id', '=', $courseId));
            })->exists();

        if ($statCheck) {
            // Remove the like if it already exists
            $delete = Like::where('user_id', '=', $user->id)
                ->where(function ($query) use ($postId, $commentId, $courseId) {
                    $query->when($postId, fn($q) => $q->where('post_id', '=', $postId))
                          ->when($commentId, fn($q) => $q->orWhere('comment_id', '=', $commentId))
                          ->when($courseId, fn($q) => $q->orWhere('course_id', '=', $courseId));
                })->delete();

            if ($delete) {
                return response()->json(['message' => 'like removed']);
            }
        }

        // Create a new like
        $newLike = new Like();
        $newLike->user_id = $user->id;

        if ($postId) {
            $newLike->post_id = $postId;
        }

        if ($commentId) {
            $newLike->comment_id = $commentId;
        }

        if ($courseId) {
            $newLike->course_id = $courseId;
        }

        $save = $newLike->save();

        if ($save) {
            return response()->json(['message' => 'like created successfully'], 200);
        }

        return response()->json(['message' => 'like creation failed'], 500);
    }

}
