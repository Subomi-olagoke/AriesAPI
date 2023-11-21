<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Models\Topic;
use App\Models\Courses;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function feed(Request $request) {
        $user = auth()->user();
        $topicIds = $user->topic()->pluck('id');
        $courses = Courses::whereIn('topic_id', $topicIds)->get();

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
}
