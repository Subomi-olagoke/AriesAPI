<?php
namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PostController extends Controller {
    public function viewSharedPost($shareKey) {
        try {
            // Log the share key access attempt
            Log::info('Attempting to view shared post', ['share_key' => $shareKey]);
            
            // Find the post with the exact share key
            $post = Post::where('share_key', $shareKey)->first();
            
            // Check if post exists
            if (!$post) {
                Log::warning('No post found with share key', ['share_key' => $shareKey]);
                return response()->json([
                    'message' => 'Post not found',
                    'share_key' => $shareKey
                ], 404);
            }
            
            // Only public posts should be shareable
            if ($post->visibility !== 'public') {
                Log::warning('Non-public post access attempted', [
                    'share_key' => $shareKey, 
                    'visibility' => $post->visibility
                ]);
                return response()->json([
                    'message' => 'This post is not available for public viewing'
                ], 403);
            }
            
            // Convert markdown to safe HTML
            $post->body = strip_tags(Str::markdown($post->body), '<p><ul><ol><li><strong><em><h3><br>');
            
            // Detailed logging for successful retrieval
            Log::info('Shared post retrieved successfully', [
                'post_id' => $post->id,
                'share_key' => $shareKey
            ]);
            
            // Include additional context for shared posts
            return response()->json([
                'post' => $post,
                'user' => $post->user ? [
                    'id' => $post->user->id,
                    'username' => $post->user->username,
                    'avatar' => $post->user->avatar
                ] : null,
                'stats' => [
                    'likes' => $post->likes()->count(),
                    'comments' => $post->comments()->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            // Comprehensive error logging
            Log::error('Error retrieving shared post', [
                'share_key' => $shareKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}