<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\LibraryUrl;
use App\Models\Post;
use App\Services\AlexPointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    protected $alexPointsService;
    
    public function __construct(AlexPointsService $alexPointsService)
    {
        $this->alexPointsService = $alexPointsService;
    }
    
    /**
     * Get comments for a commentable item (LibraryUrl, Post, etc.)
     */
    public function index(Request $request, $type, $id)
    {
        try {
            $commentableType = $this->getCommentableType($type);
            if (!$commentableType) {
                return response()->json([
                    'message' => 'Invalid commentable type'
                ], 400);
            }
            
            $comments = Comment::where('commentable_type', $commentableType)
                ->where('commentable_id', $id)
                ->with('user:id,username,first_name,last_name,avatar')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($comment) {
                    return [
                        'id' => $comment->id,
                        'body' => $comment->body,
                        'user' => [
                            'id' => $comment->user->id,
                            'username' => $comment->user->username,
                            'name' => trim(($comment->user->first_name ?? '') . ' ' . ($comment->user->last_name ?? '')),
                            'avatar' => $comment->user->avatar
                        ],
                        'created_at' => $comment->created_at->toIso8601String()
                    ];
                });
            
            return response()->json([
                'comments' => $comments
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get comments: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get comments: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create a comment on a commentable item
     */
    public function store(Request $request, $type, $id)
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:2000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $user = Auth::user();
            $commentableType = $this->getCommentableType($type);
            
            if (!$commentableType) {
                return response()->json([
                    'message' => 'Invalid commentable type'
                ], 400);
            }
            
            // Verify the commentable item exists
            $commentable = $commentableType::find($id);
            if (!$commentable) {
                return response()->json([
                    'message' => 'Item not found'
                ], 404);
            }
            
            // Create comment
            $comment = Comment::create([
                'user_id' => $user->id,
                'commentable_type' => $commentableType,
                'commentable_id' => $id,
                'body' => $request->body
            ]);
            
            // Award points for commenting
            try {
                $actionType = $type === 'library-url' ? 'comment_library_url' : 'comment_post';
                $this->alexPointsService->addPoints(
                    $user,
                    $actionType,
                    $commentableType,
                    $id,
                    "Commented on {$type}"
                );
            } catch (\Exception $e) {
                Log::warning('Failed to award points for comment: ' . $e->getMessage());
            }
            
            // Load user relationship
            $comment->load('user:id,username,first_name,last_name,avatar');
            
            return response()->json([
                'message' => 'Comment created successfully',
                'comment' => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'user' => [
                        'id' => $comment->user->id,
                        'username' => $comment->user->username,
                        'name' => trim(($comment->user->first_name ?? '') . ' ' . ($comment->user->last_name ?? '')),
                        'avatar' => $comment->user->avatar
                    ],
                    'created_at' => $comment->created_at->toIso8601String()
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create comment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create comment: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a comment
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $comment = Comment::findOrFail($id);
            
            // Only allow user to delete their own comments
            if ($comment->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 403);
            }
            
            $comment->delete();
            
            return response()->json([
                'message' => 'Comment deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete comment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete comment: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get commentable type from string
     */
    private function getCommentableType($type)
    {
        switch ($type) {
            case 'library-url':
            case 'library_url':
                return LibraryUrl::class;
            case 'post':
                return Post::class;
            default:
                return null;
        }
    }
}

