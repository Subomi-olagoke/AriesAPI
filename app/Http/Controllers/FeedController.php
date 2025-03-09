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
        
        // Combine posts from topic-related users and followed users
        // Prioritize followed users' posts
        $feedQuery = Post::with('user')
            ->where(function($query) use ($relatedUserIds, $followingIds) {
                $query->whereIn('user_id', $followingIds)
                      ->orWhereIn('user_id', $relatedUserIds);
            })
            ->orderBy('created_at', 'desc')
            ->take(50);
            
        // If we need supplementary posts to fill the feed
        $postCount = $feedQuery->count();
        
        if ($postCount < 20) {
            // Add some additional posts from outside the user's network if needed
            // but make sure they're always placed after the relevant posts
            $supplementalPosts = Post::with('user')
                ->whereNotIn('user_id', $relatedUserIds)
                ->whereNotIn('user_id', $followingIds)
                ->where('visibility', 'public')
                ->orderBy('created_at', 'desc')
                ->take(20 - $postCount)
                ->get();
                
            $feedPosts = $feedQuery->get();
            
            // Merge while maintaining the created_at order
            $posts = $feedPosts->concat($supplementalPosts)
                ->sortByDesc('created_at')
                ->values();
        } else {
            $posts = $feedQuery->get();
        }

        // Return posts with consistent ordering by creation date (newest first)
        return response()->json([
            'posts' => $posts
        ]);
    }
}