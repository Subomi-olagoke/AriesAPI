<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Models\Course;
use App\Models\Comment;
use App\Models\OpenLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Notifications\LikeNotification;

class LikeController {

    public function createLike(Request $request, Post $post = null, Comment $comment = null, Course $course = null, OpenLibrary $openLibrary = null) {
        $user = $request->user();

        $postId = $post?->id;
        $commentId = $comment?->id;
        $courseId = $course?->id;
        $openLibraryId = $openLibrary?->id;
        
        // Determine the likeable object
        $likeable = null;
        $likeableType = null;
        $likeableId = null;
        
        if ($post) {
            $likeable = $post;
            $likeableType = Post::class;
            $likeableId = $postId;
        } elseif ($comment) {
            $likeable = $comment;
            $likeableType = Comment::class;
            $likeableId = $commentId;
        } elseif ($course) {
            $likeable = $course;
            $likeableType = Course::class;
            $likeableId = $courseId;
        } elseif ($openLibrary) {
            $likeable = $openLibrary;
            $likeableType = OpenLibrary::class;
            $likeableId = $openLibraryId;
        }

        // Check if we should use polymorphic relationships
        $usePolymorphic = Schema::hasColumn('likes', 'likeable_type');
        
        // Check if the like already exists
        if ($usePolymorphic) {
            $statCheck = Like::where('user_id', $user->id)
                ->where('likeable_type', $likeableType)
                ->where('likeable_id', $likeableId)
                ->first();
        } else {
            $statCheck = Like::where('user_id', '=', $user->id)
                ->where(function ($query) use ($postId, $commentId, $courseId) {
                    $query->when($postId, fn($q) => $q->where('post_id', '=', $postId))
                          ->when($commentId, fn($q) => $q->orWhere('comment_id', '=', $commentId))
                          ->when($courseId, fn($q) => $q->orWhere('course_id', '=', $courseId));
                })->first();
        }

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
        
        if ($usePolymorphic) {
            $newLike->likeable_type = $likeableType;
            $newLike->likeable_id = $likeableId;
        } else {
            $newLike->post_id = $postId ?: null;
            $newLike->comment_id = $commentId ?: null;
            $newLike->course_id = $courseId ?: null;
        }

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
            if ($openLibrary) {
                // Optionally, load approver or other relationships if needed
            }

            $notifiable = $post?->user ?? $comment?->user ?? $course?->user; // OpenLibrary may not have a user

            if ($notifiable) {
                $notifiable->notify(new LikeNotification($post, $user, $comment, $course));
            }
            return response()->json(['message' => 'like created successfully'], 200);
        }

        return response()->json(['message' => 'like creation failed'], 500);
    }

    public function post_like_count ($postId) {
        // Check if we should use polymorphic relationships
        $usePolymorphic = Schema::hasColumn('likes', 'likeable_type');
        
        if ($usePolymorphic) {
            $count = Like::where('likeable_type', Post::class)
                         ->where('likeable_id', $postId)
                         ->count();
        } else {
            $count = Like::where('post_id', $postId)->count();
        }
        
        return response()->json([
            'success' => true,
            'post_id' => $postId,
            'like_count' => $count
        ]);
    }

    public function comment_like_count ($commentId) {
        // Check if we should use polymorphic relationships
        $usePolymorphic = Schema::hasColumn('likes', 'likeable_type');
        
        if ($usePolymorphic) {
            $count = Like::where('likeable_type', Comment::class)
                         ->where('likeable_id', $commentId)
                         ->count();
        } else {
            $count = Like::where('comment_id', $commentId)->count();
        }
        
        return response()->json([
            'success' => true,
            'comment_id' => $commentId,
            'like_count' => $count
        ]);
    }

    public function course_like_count ($courseId) {
        // Check if we should use polymorphic relationships
        $usePolymorphic = Schema::hasColumn('likes', 'likeable_type');
        
        if ($usePolymorphic) {
            $count = Like::where('likeable_type', Course::class)
                         ->where('likeable_id', $courseId)
                         ->count();
        } else {
            $count = Like::where('course_id', $courseId)->count();
        }
        
        return response()->json([
            'success' => true,
            'course_id' => $courseId,
            'like_count' => $count
        ]);
    }

    public function openlibrary_like_count($openLibraryId) {
        $usePolymorphic = Schema::hasColumn('likes', 'likeable_type');
        
        if ($usePolymorphic) {
            $count = Like::where('likeable_type', OpenLibrary::class)
                         ->where('likeable_id', $openLibraryId)
                         ->count();
        } else {
            $count = 0; // Not supported in non-polymorphic mode
        }
        
        return response()->json([
            'success' => true,
            'open_library_id' => $openLibraryId,
            'like_count' => $count
        ]);
    }

}
