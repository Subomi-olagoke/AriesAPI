<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function feed() {
        $user = auth()->user();

        // Get the user's topic IDs
        $topicIds = $user->topic()->pluck('topic_id');

        // Get related user IDs based on common topics
        $relatedUserIds = User::whereHas('topic', fn($query) =>
            $query->whereIn('topics.id', $topicIds)
        )->pluck('id');

        // Get posts from related users
        $relatedPosts = Post::with('user')
            ->whereIn('user_id', $relatedUserIds)
            ->latest()
            ->limit(10)
            ->get();

        // Get some random posts as well for variety
        $randomPosts = Post::with('user')
            ->whereNotIn('user_id', $relatedUserIds)
            ->inRandomOrder()
            ->limit(10)
            ->get();

        // Merge posts but don't shuffle
        $posts = $relatedPosts->merge($randomPosts);

        // Return only the posts
        return response()->json([
            'posts' => $posts
        ]);
    }


}