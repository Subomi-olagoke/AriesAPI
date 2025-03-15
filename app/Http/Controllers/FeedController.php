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

        // Get posts from users the current user is following
        $followingIds = $user->following()->pluck('followeduser');
        
        // Get all public posts ordered by created_at descending (newest first)
        $posts = Post::with('user')
            ->where('visibility', 'public')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();
            
        // Add relationship flags to each post
        foreach ($posts as $post) {
            $post->is_from_followed_user = $followingIds->contains($post->user_id);
            $post->is_from_related_topic = $relatedUserIds->contains($post->user_id);
        }

        // Return posts with newest first
        return response()->json([
            'posts' => $posts
        ]);
    }
}