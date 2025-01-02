<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Models\Topic;
use App\Models\Course;
use App\Models\Courses;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function feed() {
        $user = auth()->user();
        $topicIds = $user->topic()->pluck('topic_id');
        $courses = Course::whereIn('topic_id', $topicIds)->get();

        $posts = Post::with('user')
        ->whereIn('user_id', $user->following()->pluck('id'))
        ->latest()
        ->get();

        if($user->role == User::ROLE_LEARNER) {
            return response()->json([
                'posts' => $posts,
                'courses' => $courses
            ]);
        }

        return response()->json([
            'posts' => $posts
        ]);

    }

    public function suggestedCourses() {
        $user = auth()->user();
        $followedId = $user->following()->pluck('id');
        $peerLikes = Like::whereIn('user_id', $followedId)
                    ->whereNotNull('course_id')
                    ->pluck('course_id');

        $courses = Course::whereIn('id', $peerLikes)->get();
        return response()->json([
            'suggested_courses' => $courses
        ],200);

    }

    public function topCourses() {
        $topCourses = Course::withCount('likes')
            ->orderBy('likes_count', 'description')
            ->take(10)
            ->get();

        return response()->json([
            'top_courses' => $topCourses,
        ]);
    }

}
