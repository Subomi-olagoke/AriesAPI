<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Course;
use App\Models\LibraryUrl;
use App\Models\User;
use App\Notifications\CommentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Display comments for a specific item.
     */
    public function index(Request $request, $type, $id)
    {
        try {
            $commentableType = $this->getCommentableType($type);
            
            if (!$commentableType) {
                return response()->json([
                    'message' => 'Invalid comment type'
                ], 400);
            }
            
            $comments = Comment::where('commentable_type', $commentableType)
                ->where('commentable_id', $id)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json($comments);
        } catch (\Exception $e) {
            Log::error('Failed to fetch comments: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch comments'
            ], 500);
        }
    }

    /**
     * Store a newly created comment.
     */
    public function store(Request $request, $type = 'post', $id = null)
    {
        // Handle route where type is in the URL path
        if ($id === null && is_numeric($type)) {
            $id = $type;
            $type = 'post';
        }
        
        $validator = Validator::make($request->all(), [
            'content' => 'required_without:body|string|max:5000',
            'body' => 'required_without:content|string|max:5000',
            'parent_id' => 'nullable|integer|exists:comments,id',
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
                    'message' => 'Invalid comment type'
                ], 400);
            }
            
            // Find the commentable item
            $commentable = $commentableType::findOrFail($id);
            
            // Create the comment
            $comment = new Comment();
            $comment->user_id = $user->id;
            $comment->commentable_type = $commentableType;
            $comment->commentable_id = $id;
            $comment->body = $request->input('content') ?? $request->input('body');
            $comment->parent_id = $request->input('parent_id');
            $comment->save();
            
            // Load the user relationship
            $comment->load('user');
            
            // Send notification to content owner (if not self-commenting)
            try {
                $contentOwner = null;
                
                // Determine content owner based on commentable type
                if ($commentable instanceof Post) {
                    $contentOwner = $commentable->user;
                } elseif ($commentable instanceof Course) {
                    $contentOwner = $commentable->user;
                } elseif ($commentable instanceof LibraryUrl) {
                    if ($commentable->created_by) {
                        $contentOwner = User::find($commentable->created_by);
                    }
                } elseif ($commentable instanceof Comment) {
                    // For comment replies, notify the parent comment author
                    $contentOwner = $commentable->user;
                }
                
                // Only notify if content owner exists and is different from commenter
                if ($contentOwner && $contentOwner->id !== $user->id) {
                    $contentOwner->notify(new CommentNotification(
                        $user,
                        $comment,
                        $commentableType,
                        $id
                    ));
                    Log::info("Sent comment notification to content owner: {$contentOwner->id}");
                }
            } catch (\Exception $e) {
                Log::warning('Failed to send comment notification: ' . $e->getMessage());
            }
            
            return response()->json($comment, 201);
            
        } catch (\Exception $e) {
            Log::error('Failed to create comment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified comment.
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $comment = Comment::findOrFail($id);
            
            // Check if user owns the comment or is admin
            if ($comment->user_id !== $user->id && !$user->isAdmin) {
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
                'message' => 'Failed to delete comment'
            ], 500);
        }
    }

    /**
     * Get the commentable type class from string.
     */
    private function getCommentableType($type): ?string
    {
        return match($type) {
            'post', 'posts' => Post::class,
            'course', 'courses' => Course::class,
            'library-url', 'library_url', 'libraryurl' => LibraryUrl::class,
            default => null
        };
    }
}
