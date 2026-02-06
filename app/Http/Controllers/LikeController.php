<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Models\Course;
use App\Models\Comment;
use App\Models\OpenLibrary;
use App\Models\LibraryUrl;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Notifications\LikeNotification;
use App\Services\AlexPointsService;

class LikeController {
    protected $alexPointsService;
    
    public function __construct(AlexPointsService $alexPointsService)
    {
        $this->alexPointsService = $alexPointsService;
    }

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
                
                // Award points to the user who received the like
                // Only award if it's not the same user liking their own content
                if ($notifiable->id !== $user->id) {
                    try {
                        $referenceType = $post ? Post::class : ($comment ? Comment::class : ($course ? Course::class : null));
                        $referenceId = $post?->id ?? $comment?->id ?? $course?->id ?? null;
                        
                        if ($referenceType && $referenceId) {
                            $this->alexPointsService->addPoints(
                                $notifiable,
                                'receive_like',
                                $referenceType,
                                $referenceId,
                                "Received a like on your content"
                            );
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to award points for receiving like: ' . $e->getMessage());
                    }
                }
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

    /**
     * Like or unlike a library URL
     */
    public function likeLibraryUrl(Request $request, $urlId)
    {
        $user = $request->user();
        $libraryUrl = LibraryUrl::findOrFail($urlId);
        
        $usePolymorphic = Schema::hasColumn('likes', 'likeable_type');
        
        if ($usePolymorphic) {
            $existingLike = Like::where('user_id', $user->id)
                ->where('likeable_type', LibraryUrl::class)
                ->where('likeable_id', $urlId)
                ->first();
            
            if ($existingLike) {
                $existingLike->delete();
                return response()->json(['message' => 'Like removed'], 200);
            }
            
            $like = new Like();
            $like->user_id = $user->id;
            $like->likeable_type = LibraryUrl::class;
            $like->likeable_id = $urlId;
            $like->save();
            
            return response()->json(['message' => 'Like created successfully'], 200);
        }
        
        return response()->json(['message' => 'Like functionality not available'], 400);
    }

    /**
     * Get like count for a library URL
     */
    public function libraryUrlLikeCount($urlId)
    {
        $usePolymorphic = Schema::hasColumn('likes', 'likeable_type');
        
        if ($usePolymorphic) {
            $count = Like::where('likeable_type', LibraryUrl::class)
                         ->where('likeable_id', $urlId)
                         ->count();
        } else {
            $count = 0;
        }
        
        return response()->json([
            'success' => true,
            'library_url_id' => $urlId,
            'like_count' => $count
        ]);
    }

    /**
     * Vote on a library URL (upvote/downvote) - Reddit Style with Toggle
     * 
     * Behavior:
     * - If no vote exists: Create new vote
     * - If same vote exists: Remove vote (toggle off)
     * - If opposite vote exists: Switch to new vote
     */
    public function voteLibraryUrl(Request $request, $urlId)
    {
        $user = $request->user();
        $voteType = $request->input('vote_type'); // 'up' or 'down'
        
        // Validate vote type
        if (!in_array($voteType, ['up', 'down'])) {
            return response()->json([
                'message' => 'Invalid vote type. Must be "up" or "down"'
            ], 400);
        }
        
        $libraryUrl = LibraryUrl::findOrFail($urlId);
        
        try {
            DB::beginTransaction();
            
            // Check for existing vote in library_url_votes table
            $existingVote = DB::table('library_url_votes')
                ->where('user_id', $user->id)
                ->where('library_url_id', $urlId)
                ->first();
            
            $action = '';
            
            if ($existingVote) {
                if ($existingVote->vote_type === $voteType) {
                    // Same vote - REMOVE (toggle off)
                    DB::table('library_url_votes')
                        ->where('user_id', $user->id)
                        ->where('library_url_id', $urlId)
                        ->delete();
                    $action = 'removed';
                } else {
                    // Opposite vote - SWITCH
                    DB::table('library_url_votes')
                        ->where('user_id', $user->id)
                        ->where('library_url_id', $urlId)
                        ->update([
                            'vote_type' => $voteType,
                            'updated_at' => now()
                        ]);
                    $action = 'switched';
                }
            } else {
                // No existing vote - CREATE
                DB::table('library_url_votes')->insert([
                    'user_id' => $user->id,
                    'library_url_id' => $urlId,
                    'vote_type' => $voteType,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $action = 'created';
                
                // Award points to the user who added the URL (if different from voter)
                try {
                    if ($libraryUrl->created_by && $libraryUrl->created_by !== $user->id) {
                        $contentOwner = User::find($libraryUrl->created_by);
                        if ($contentOwner && $voteType === 'up') {
                            $this->alexPointsService->addPoints(
                                $contentOwner,
                                'receive_upvote',
                                LibraryUrl::class,
                                $urlId,
                                "Received an upvote on library content"
                            );
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to award points for upvote: ' . $e->getMessage());
                }
            }
            
            // Calculate current vote counts from library_url_votes table
            $upvotes = DB::table('library_url_votes')
                ->where('library_url_id', $urlId)
                ->where('vote_type', 'up')
                ->count();
                
            $downvotes = DB::table('library_url_votes')
                ->where('library_url_id', $urlId)
                ->where('vote_type', 'down')
                ->count();
            
            DB::commit();
            
            return response()->json([
                'message' => "Vote {$action}",
                'action' => $action,
                'vote_type' => $voteType,
                'upvotes_count' => $upvotes,
                'downvotes_count' => $downvotes,
                'net_score' => $upvotes - $downvotes,
                'user_vote' => $action === 'removed' ? null : $voteType
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vote failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Vote failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    
    /**
     * Get vote counts and user's vote state for a library URL
     */
    public function getLibraryUrlVotes(Request $request, $urlId)
    {
        $user = $request->user();
        
        try {
            // Get vote counts from library_url_votes table
            $upvotes = DB::table('library_url_votes')
                ->where('library_url_id', $urlId)
                ->where('vote_type', 'up')
                ->count();
                
            $downvotes = DB::table('library_url_votes')
                ->where('library_url_id', $urlId)
                ->where('vote_type', 'down')
                ->count();
            
            // Get user's vote if exists
            $userVote = null;
            if ($user) {
                $vote = DB::table('library_url_votes')
                    ->where('user_id', $user->id)
                    ->where('library_url_id', $urlId)
                    ->first();
                    
                $userVote = $vote ? $vote->vote_type : null;
            }
            
            return response()->json([
                'success' => true,
                'upvotes_count' => $upvotes,
                'downvotes_count' => $downvotes,
                'net_score' => $upvotes - $downvotes,
                'user_vote' => $userVote
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get vote counts: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get vote counts'
            ], 500);
        }
    }

}
