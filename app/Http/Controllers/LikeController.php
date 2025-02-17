<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Models\Likes;
use App\Models\Course;
use App\Models\Comment;
use App\Models\comments;
use Illuminate\Http\Request;
use App\Notifications\LikeNotification;

class LikeController {

    public function createLike(Request $request, Post $post = null, Comment $comment = null, Course $course = null) {
        $user = $request->user();

        $postId = $post?->id;
        $commentId = $comment?->id;
        $courseId = $course?->id;

        //Check if the like already exists
        $statCheck = Like::where('user_id', '=', $user->id)
            ->where(function ($query) use ($postId, $commentId, $courseId) {
                $query->when($postId, fn($q) => $q->where('post_id', '=', $postId))
                      ->when($commentId, fn($q) => $q->orWhere('comment_id', '=', $commentId))
                      ->when($courseId, fn($q) => $q->orWhere('course_id', '=', $courseId));
            })->first();

        if ($statCheck) {
            //Remove like if it already exists
           $delete = $statCheck->delete();
            if ($delete) {
                return response()->json(['message' => 'like removed']);
            }
        }

        // Create a new like
        $newLike = new Like();
        $newLike->user_id = $user->id;
        $newLike->post_id = $postId ?: null;
        $newLike->comment_id = $commentId ?: null;
        $newLike->course_id = $courseId ?: null;

        $save = $newLike->save();

        if ($save) {
            if ($post) {
                $post->load('user');
            }
            if ($comment) {
                $comment->load('user');
            }
            if ($course) {
                $course->load('user');
            }

            $notifiable = $post?->user ?? $comment?->user ?? $course?->user;

            if ($notifiable) {
                $notifiable->notify(new LikeNotification($post, $user, $comment, $course));
            }
            return response()->json(['message' => 'like created successfully'], 200);
        }

        return response()->json(['message' => 'like creation failed'], 500);
    }

    public function post_like_count ($postId) {
        $count = Like::where('post_id', $postId)->count();
        return response()->json([
            'post_id' => $postId,
            'like_count' => $count
        ]);
    }

    public function comment_like_count ($commentId) {
        $count = Like::where('comment_id', $commentId)->count();
        return response()->json([
            'comment_id' => $commentId,
            'like_count' => $count
        ]);
    }

    public function course_like_count ($courseId) {
        $count = Like::where('course_id', $courseId)->count();
        return response()->json([
            'course_id' => $courseId,
            'like_count' => $count
        ]);
    }

}
