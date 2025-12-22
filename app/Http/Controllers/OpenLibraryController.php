<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\LibraryUrl;
use App\Services\OpenLibraryService;
use App\Services\UrlFetchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class OpenLibraryController extends Controller
{
    protected $libraryService;
    protected $urlFetchService;
    
    /**
     * Create a new controller instance.
     */
    public function __construct(OpenLibraryService $libraryService, UrlFetchService $urlFetchService)
    {
        $this->libraryService = $libraryService;
        $this->urlFetchService = $urlFetchService;
    }
    
    /**
     * Display a listing of libraries.
     */
    public function index()
    {
        try {
            // Check if the is_approved column exists in the schema
            $hasIsApprovedColumn = Schema::hasColumn('open_libraries', 'is_approved');
            $hasApprovalStatusColumn = Schema::hasColumn('open_libraries', 'approval_status');
            
            // Build query based on available columns
            $query = OpenLibrary::query();
            
            if ($hasIsApprovedColumn) {
                $query->where('is_approved', true);
            }
            
            if ($hasApprovalStatusColumn) {
                $query->where('approval_status', 'approved');
            }
            
            // Get results ordered by creation date
            $libraries = $query->orderBy('created_at', 'desc')->get();
            
            // Format the response to match iOS expectations
            $formattedLibraries = $libraries->map(function ($library) {
                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'type' => $library->type,
                    'thumbnailUrl' => $library->thumbnail_url,
                    'coverImageUrl' => $library->cover_image_url,
                    'courseId' => $library->course_id,
                    'criteria' => $library->criteria,
                    'keywords' => $library->keywords,
                    'isApproved' => $library->is_approved,
                    'approvalStatus' => $library->approval_status,
                    'approvalDate' => $library->approval_date,
                    'approvedBy' => $library->approved_by,
                    'rejectionReason' => $library->rejection_reason,
                    'coverPrompt' => $library->cover_prompt,
                    'aiGenerated' => $library->ai_generated,
                    'aiGenerationDate' => $library->ai_generation_date,
                    'aiModelUsed' => $library->ai_model_used,
                    'hasAiCover' => $library->has_ai_cover,
                    'createdAt' => $library->created_at,
                    'updatedAt' => $library->updated_at,
                    'contents' => $library->contents ? $library->contents->map(function ($content) {
                        return [
                            'id' => $content->id,
                            'libraryId' => $content->library_id,
                            'contentId' => $content->content_id,
                            'contentType' => $content->content_type,
                            'relevanceScore' => $content->relevance_score,
                            'createdAt' => $content->created_at,
                            'updatedAt' => $content->updated_at,
                            'contentData' => $content->content ? [
                                'id' => $content->content->id,
                                'title' => $content->content->title ?? null,
                                'body' => $content->content->body ?? null,
                                'mediaType' => $content->content->media_type ?? null,
                                'mediaLink' => $content->content->media_link ?? null,
                                'mediaThumbnail' => $content->content->media_thumbnail ?? null,
                                'visibility' => $content->content->visibility ?? null,
                                'user' => $content->content->user ? [
                                    'id' => $content->content->user->id,
                                    'firstName' => $content->content->user->first_name,
                                    'lastName' => $content->content->user->last_name,
                                    'username' => $content->content->user->username,
                                    'avatar' => $content->content->user->avatar,
                                    'isVerified' => $content->content->user->is_verified ?? false,
                                    'role' => $content->content->user->role,
                                    'alex_points' => $content->content->user->alex_points
                                ] : null
                            ] : null
                        ];
                    }) : [],
                    'user' => null,
                    'userId' => null
                ];
            });
            
            return response()->json([
                'libraries' => $formattedLibraries
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving libraries: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving libraries: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get libraries created by or contributed to by the authenticated user.
     */
    public function getUserLibraries()
    {
        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            // Get libraries created by the user
            $createdLibraries = OpenLibrary::where('creator_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Get library IDs where user has contributed URLs
            $contributedLibraryIds = LibraryUrl::where('added_by', $userId)
                ->distinct()
                ->pluck('library_id');
            
            // Get contributed libraries (excluding ones already created by user)
            $contributedLibraries = OpenLibrary::whereIn('id', $contributedLibraryIds)
                ->where(function($query) use ($userId) {
                    $query->whereNull('creator_id')
                        ->orWhere('creator_id', '!=', $userId);
                })
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Merge and format the libraries
            $allLibraries = $createdLibraries->merge($contributedLibraries)->unique('id');
            
            $formattedLibraries = $allLibraries->map(function ($library) use ($userId) {
                // Count user's contributions to this library
                $contributionCount = LibraryUrl::where('library_id', $library->id)
                    ->where('added_by', $userId)
                    ->count();
                
                // Count total content in library
                $contentCount = LibraryUrl::where('library_id', $library->id)->count();
                
                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'type' => $library->type,
                    'thumbnailUrl' => $library->thumbnail_url,
                    'coverImageUrl' => $library->cover_image_url,
                    'isCreator' => $library->creator_id === $userId,
                    'contributionCount' => $contributionCount,
                    'contentCount' => $contentCount,
                    'createdAt' => $library->created_at,
                    'updatedAt' => $library->updated_at,
                ];
            })->values();
            
            return response()->json([
                'success' => true,
                'libraries' => $formattedLibraries
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching user libraries: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user libraries: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get personalized library sections for the feed.
     * Returns libraries organized into sections:
     * - For You: Personalized based on user's followed libraries
     * - Private Libraries: Libraries created or followed by user
     * - Because You Liked: Similar to recently followed library
     * - More to Explore: Discovery of other approved libraries
     */
    public function getSections()
    {
        try {
            $user = Auth::user();
            $userId = $user ? $user->id : null;
            
            // Get all approved libraries
            // Include libraries that are:
            // 1. Explicitly approved (is_approved = true OR approval_status = 'approved')
            // 2. Older libraries without approval fields set (treat as approved)
            // 3. Exclude only libraries that are explicitly rejected
            $hasIsApprovedColumn = Schema::hasColumn('open_libraries', 'is_approved');
            $hasApprovalStatusColumn = Schema::hasColumn('open_libraries', 'approval_status');
            
            $query = OpenLibrary::query();
            
            if ($hasIsApprovedColumn && $hasApprovalStatusColumn) {
                // Both columns exist: include if approved by either field, or if approval_status is null (older libraries)
                // Exclude only if explicitly rejected
                $query->where(function($q) {
                    $q->where('is_approved', true)
                      ->orWhere('approval_status', 'approved')
                      ->orWhere(function($nullQ) {
                          // Older libraries without approval_status set - treat as approved
                          // This includes libraries where approval_status is null (regardless of is_approved)
                          $nullQ->whereNull('approval_status');
                      });
                });
                // Exclude explicitly rejected libraries
                if ($hasApprovalStatusColumn) {
                    $query->where(function($q) {
                        $q->whereNull('approval_status')
                          ->orWhere('approval_status', '!=', 'rejected');
                    });
                }
            } elseif ($hasIsApprovedColumn) {
                // Only is_approved column exists
                $query->where(function($q) {
                    $q->where('is_approved', true)
                      ->orWhereNull('is_approved');
                });
            } elseif ($hasApprovalStatusColumn) {
                // Only approval_status column exists
                $query->where(function($q) {
                    $q->where('approval_status', 'approved')
                      ->orWhere(function($nullQ) {
                          $nullQ->whereNull('approval_status')
                                ->orWhere('approval_status', '!=', 'rejected');
                      });
                });
            }
            // If neither column exists, include all libraries (no filtering)
            
            $allLibraries = $query->whereNull('deleted_at')->orderBy('created_at', 'desc')->get();
            
            // Get user's followed library IDs
            $followedLibraryIds = [];
            $recentlyFollowedLibrary = null;
            
            if ($userId) {
                $followedLibraryIds = DB::table('library_follows')
                    ->where('user_id', $userId)
                    ->pluck('library_id')
                    ->toArray();
                
                // Get most recently followed library for "Because You Liked"
                $recentFollow = DB::table('library_follows')
                    ->where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($recentFollow) {
                    $recentlyFollowedLibrary = OpenLibrary::find($recentFollow->library_id);
                }
            }
            
            // Get user's created libraries
            $createdLibraryIds = [];
            if ($userId && Schema::hasColumn('open_libraries', 'creator_id')) {
                $createdLibraryIds = OpenLibrary::where('creator_id', $userId)
                    ->pluck('id')
                    ->toArray();
            }
            
            // Helper function to format library for iOS
            $formatLibrary = function ($library) {
                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'type' => $library->type,
                    'thumbnailUrl' => $library->thumbnail_url,
                    'coverImageUrl' => $library->cover_image_url,
                    'courseId' => $library->course_id,
                    'criteria' => $library->criteria,
                    'keywords' => $library->keywords,
                    'isApproved' => $library->is_approved,
                    'approvalStatus' => $library->approval_status,
                    'approvalDate' => $library->approval_date,
                    'approvedBy' => $library->approved_by,
                    'rejectionReason' => $library->rejection_reason,
                    'coverPrompt' => $library->cover_prompt,
                    'aiGenerated' => $library->ai_generated,
                    'aiGenerationDate' => $library->ai_generation_date,
                    'aiModelUsed' => $library->ai_model_used,
                    'hasAiCover' => $library->has_ai_cover,
                    'createdAt' => $library->created_at,
                    'updatedAt' => $library->updated_at,
                    'contents' => [],
                    'user' => null,
                    'userId' => null
                ];
            };
            
            $sections = [];
            $usedLibraryIds = [];
            
            // ========== SECTION 1: FOR YOU ==========
            // Personalized recommendations based on followed libraries' keywords/categories
            // Or high-engagement libraries if no follows
            $forYouLibraries = collect([]);
            
            if (!empty($followedLibraryIds)) {
                // Get keywords from followed libraries
                $followedKeywords = [];
                foreach ($followedLibraryIds as $libId) {
                    $lib = $allLibraries->firstWhere('id', $libId);
                    if ($lib && $lib->keywords) {
                        $followedKeywords = array_merge($followedKeywords, (array)$lib->keywords);
                    }
                }
                $followedKeywords = array_unique($followedKeywords);
                
                // Find libraries with matching keywords that user doesn't follow
                $forYouLibraries = $allLibraries->filter(function ($lib) use ($followedLibraryIds, $followedKeywords) {
                    if (in_array($lib->id, $followedLibraryIds)) return false;
                    if (empty($lib->keywords)) return false;
                    
                    $libKeywords = (array)$lib->keywords;
                    return !empty(array_intersect($libKeywords, $followedKeywords));
                })->values();
            }
            
            // If not enough recommendations, fill with popular libraries
            if ($forYouLibraries->count() < 5) {
                $remaining = 5 - $forYouLibraries->count();
                $existingIds = $forYouLibraries->pluck('id')->toArray();
                
                // Get libraries with most followers
                $popularIds = DB::table('library_follows')
                    ->select('library_id', DB::raw('COUNT(*) as follower_count'))
                    ->groupBy('library_id')
                    ->orderByDesc('follower_count')
                    ->limit($remaining + 10)
                    ->pluck('library_id')
                    ->toArray();
                
                $additionalLibraries = $allLibraries->filter(function ($lib) use ($existingIds, $followedLibraryIds, $popularIds) {
                    return !in_array($lib->id, $existingIds) 
                        && !in_array($lib->id, $followedLibraryIds)
                        && in_array($lib->id, $popularIds);
                })->take($remaining);
                
                $forYouLibraries = $forYouLibraries->merge($additionalLibraries);
            }
            
            // If still not enough, just take recent libraries
            if ($forYouLibraries->count() < 5) {
                $remaining = 5 - $forYouLibraries->count();
                $existingIds = $forYouLibraries->pluck('id')->toArray();
                
                $additionalLibraries = $allLibraries->filter(function ($lib) use ($existingIds, $followedLibraryIds) {
                    return !in_array($lib->id, $existingIds) && !in_array($lib->id, $followedLibraryIds);
                })->take($remaining);
                
                $forYouLibraries = $forYouLibraries->merge($additionalLibraries);
            }
            
            if ($forYouLibraries->isNotEmpty()) {
                $formattedForYou = $forYouLibraries->map($formatLibrary)->values()->toArray();
                $usedLibraryIds = array_merge($usedLibraryIds, $forYouLibraries->pluck('id')->toArray());
                
                $sections[] = [
                    'id' => 'for_you',
                    'title' => 'For You',
                    'type' => 'personalized',
                    'source_library_id' => null,
                    'source_library_name' => null,
                    'libraries' => $formattedForYou
                ];
            }
            
            // ========== SECTION 2: PRIVATE LIBRARIES ==========
            // Private-type libraries created by users that the current user follows
            $privateLibraries = collect([]);
            
            if ($userId) {
                // Get IDs of users the current user follows
                $followedUserIds = DB::table('follows')
                    ->where('user_id', $userId)
                    ->pluck('followeduser')
                    ->toArray();
                
                if (!empty($followedUserIds) && Schema::hasColumn('open_libraries', 'creator_id')) {
                    // Get private-type libraries created by followed users
                    $privateLibraries = $allLibraries->filter(function ($lib) use ($followedUserIds) {
                        // Library must be type 'private' and created by a followed user
                        return strtolower($lib->type ?? '') === 'private' 
                            && in_array($lib->creator_id, $followedUserIds);
                    })->values();
                }
            }
            
            if ($privateLibraries->isNotEmpty()) {
                $formattedPrivate = $privateLibraries->map($formatLibrary)->values()->toArray();
                
                $sections[] = [
                    'id' => 'private_libraries',
                    'title' => 'Private Libraries',
                    'type' => 'private',
                    'source_library_id' => null,
                    'source_library_name' => null,
                    'libraries' => $formattedPrivate
                ];
            }
            
            // ========== SECTION 3: BECAUSE YOU LIKED ==========
            // Similar to most recently followed library
            if ($recentlyFollowedLibrary) {
                $sourceKeywords = (array)($recentlyFollowedLibrary->keywords ?? []);
                $sourceName = $recentlyFollowedLibrary->name;
                
                $similarLibraries = $allLibraries->filter(function ($lib) use ($recentlyFollowedLibrary, $followedLibraryIds, $usedLibraryIds, $sourceKeywords) {
                    // Exclude the source library and already followed
                    if ($lib->id === $recentlyFollowedLibrary->id) return false;
                    if (in_array($lib->id, $followedLibraryIds)) return false;
                    if (in_array($lib->id, $usedLibraryIds)) return false;
                    
                    // Match on keywords
                    if (!empty($sourceKeywords) && !empty($lib->keywords)) {
                        $libKeywords = (array)$lib->keywords;
                        return !empty(array_intersect($libKeywords, $sourceKeywords));
                    }
                    
                    return false;
                })->values();
                
                // If not enough matches, just take random others
                if ($similarLibraries->count() < 5) {
                    $remaining = 5 - $similarLibraries->count();
                    $existingIds = array_merge($usedLibraryIds, $similarLibraries->pluck('id')->toArray(), $followedLibraryIds);
                    
                    $additionalLibraries = $allLibraries->filter(function ($lib) use ($existingIds) {
                        return !in_array($lib->id, $existingIds);
                    })->take($remaining);
                    
                    $similarLibraries = $similarLibraries->merge($additionalLibraries);
                }
                
                if ($similarLibraries->isNotEmpty()) {
                    $formattedSimilar = $similarLibraries->map($formatLibrary)->values()->toArray();
                    $usedLibraryIds = array_merge($usedLibraryIds, $similarLibraries->pluck('id')->toArray());
                    
                    $sections[] = [
                        'id' => 'because_you_liked_' . $recentlyFollowedLibrary->id,
                        'title' => 'Because You Liked ' . $sourceName,
                        'type' => 'related',
                        'source_library_id' => (string)$recentlyFollowedLibrary->id,
                        'source_library_name' => $sourceName,
                        'libraries' => $formattedSimilar
                    ];
                }
            }
            
            // ========== SECTION 4: MORE TO EXPLORE ==========
            // Discovery - other libraries not yet shown
            $exploreLibraries = $allLibraries->filter(function ($lib) use ($usedLibraryIds, $followedLibraryIds, $createdLibraryIds) {
                return !in_array($lib->id, $usedLibraryIds) 
                    && !in_array($lib->id, $followedLibraryIds)
                    && !in_array($lib->id, $createdLibraryIds);
            })->values();
            
            if ($exploreLibraries->isNotEmpty()) {
                $formattedExplore = $exploreLibraries->map($formatLibrary)->values()->toArray();
                
                $sections[] = [
                    'id' => 'more_to_explore',
                    'title' => 'More to Explore',
                    'type' => 'discovery',
                    'source_library_id' => null,
                    'source_library_name' => null,
                    'libraries' => $formattedExplore
                ];
            }
            
            return response()->json([
                'sections' => $sections
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error retrieving library sections: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving library sections: ' . $e->getMessage(),
                'sections' => []
            ], 500);
        }
    }
    
    /**
     * Store a newly created library.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:50',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:5048', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $coverImageUrl = null;
            $thumbnailUrl = null;

            // Handle Image Upload
            if ($request->hasFile('image')) {
                try {
                    // Get Cloudinary instance from container
                    $cloudinary = app(\Cloudinary\Cloudinary::class);
                    
                    // Upload to Cloudinary
                    $result = $cloudinary->uploadApi()->upload(
                        $request->file('image')->getRealPath(), 
                        ['folder' => 'libraries']
                    );
                    
                    $coverImageUrl = $result['secure_url'];
                    $thumbnailUrl = $coverImageUrl; // Use same image for thumbnail for now
                } catch (\Exception $e) {
                    Log::error('Cloudinary upload failed: ' . $e->getMessage());
                    // Continue without image or fail? Deciding to fail to inform user.
                    throw new \Exception('Failed to upload image: ' . $e->getMessage());
                }
            }

            // Auto-approve libraries created by admin users
            $user = Auth::user();
            $isAdmin = $user && $user->isAdmin;
            
            // Create library
            $library = OpenLibrary::create([
                'name' => $request->name,
                'description' => $request->description,
                'type' => $request->category ?? 'manual', // Map category to type
                'cover_image_url' => $coverImageUrl,
                'thumbnail_url' => $thumbnailUrl,
                'creator_id' => $user ? $user->id : null,
                'is_approved' => $isAdmin, // Auto-approve for admins
                'approval_status' => $isAdmin ? 'approved' : 'pending'
            ]);
            
            return response()->json([
                'message' => 'Library created successfully',
                'library' => $library
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Library creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Library creation failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get library private page with links and user interaction data
     */
    public function getPrivatePage(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $library = OpenLibrary::findOrFail($id);
            
            // Check if user is following this library
            $isFollowing = DB::table('library_follows')
                ->where('user_id', $user->id)
                ->where('library_id', $library->id)
                ->exists();
            
            // Get library URLs with like counts and user's like status
            $contents = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_type', LibraryUrl::class)
                ->orderBy('relevance_score', 'desc')
                ->get();
            
            $formattedLinks = [];
            foreach ($contents as $content) {
                $libraryUrl = LibraryUrl::with('creator')->find($content->content_id);
                if ($libraryUrl) {
                    // Get like count
                    $likeCount = DB::table('likes')
                        ->where('likeable_type', LibraryUrl::class)
                        ->where('likeable_id', $libraryUrl->id)
                        ->count();
                    
                    // Check if user has liked this link
                    $userLiked = DB::table('likes')
                        ->where('user_id', $user->id)
                        ->where('likeable_type', LibraryUrl::class)
                        ->where('likeable_id', $libraryUrl->id)
                        ->exists();
                    
                    $formattedLinks[] = [
                        'id' => $libraryUrl->id,
                        'title' => $libraryUrl->title,
                        'url' => $libraryUrl->url,
                        'summary' => $libraryUrl->summary,
                        'notes' => $libraryUrl->notes,
                        'like_count' => $likeCount,
                        'user_liked' => $userLiked,
                        'created_by' => $libraryUrl->creator ? [
                            'id' => $libraryUrl->creator->id,
                            'username' => $libraryUrl->creator->username
                        ] : null,
                        'created_at' => $libraryUrl->created_at
                    ];
                }
            }
            
            return response()->json([
                'library' => [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'thumbnail_url' => $library->thumbnail_url,
                    'cover_image_url' => $library->cover_image_url,
                    'type' => $library->type,
                    'is_following' => $isFollowing
                ],
                'links' => $formattedLinks
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Get library private page failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get library page: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Display the specified library with its content.
     */
    public function show($id)
    {
        try {
            $library = OpenLibrary::findOrFail($id);
            
            // Get content with appropriate relationships
            $contents = DB::table('library_content')
                ->where('library_id', $library->id)
                ->orderBy('relevance_score', 'desc')
                ->get();
                
            $formattedContents = [];
            foreach ($contents as $content) {
                $contentItem = null;
                
                if ($content->content_type === Course::class) {
                    $contentItem = Course::with('user', 'topic')->find($content->content_id);
                    if ($contentItem) {
                        $formattedContents[] = [
                            'id' => $contentItem->id,
                            'title' => $contentItem->title,
                            'description' => $contentItem->description,
                            'thumbnail_url' => $contentItem->thumbnail_url,
                            'user' => $contentItem->user ? [
                                'id' => $contentItem->user->id,
                                'username' => $contentItem->user->username
                            ] : null,
                            'topic' => $contentItem->topic ? [
                                'id' => $contentItem->topic->id,
                                'name' => $contentItem->topic->name
                            ] : null,
                            'type' => 'course',
                            'relevance_score' => $content->relevance_score
                        ];
                    }
                } 
                elseif ($content->content_type === Post::class) {
                    $contentItem = Post::with('user')->find($content->content_id);
                    if ($contentItem) {
                        $formattedContents[] = [
                            'id' => $contentItem->id,
                            'title' => $contentItem->title,
                            'body' => substr($contentItem->body, 0, 200) . '...',
                            'media_link' => $contentItem->media_link,
                            'media_type' => $contentItem->media_type,
                            'user' => $contentItem->user ? [
                                'id' => $contentItem->user->id,
                                'username' => $contentItem->user->username
                            ] : null,
                            'type' => 'post',
                            'relevance_score' => $content->relevance_score
                        ];
                    }
                }
                elseif ($content->content_type === LibraryUrl::class) {
                    $contentItem = LibraryUrl::find($content->content_id);
                    if ($contentItem) {
                        $formattedContents[] = [
                            'id' => $contentItem->id,
                            'title' => $contentItem->title,
                            'url' => $contentItem->url,
                            'description' => $contentItem->summary,
                            'notes' => $contentItem->notes,
                            'type' => 'url',
                            'relevance_score' => $content->relevance_score,
                            'created_at' => $contentItem->created_at
                        ];
                    }
                }
            }
            
            // For backward compatibility, also add URLs from the url_items array
            // This ensures we don't miss any URLs that were added before the migration
            $urlItems = $library->url_items ?? [];
            
            foreach ($urlItems as $urlItem) {
                // Check if this URL is already included (based on the URL itself)
                $url = $urlItem['url'] ?? '';
                $alreadyIncluded = false;
                
                foreach ($formattedContents as $content) {
                    if ($content['type'] === 'url' && isset($content['url']) && $content['url'] === $url) {
                        $alreadyIncluded = true;
                        break;
                    }
                }
                
                // Only add if not already included
                if (!$alreadyIncluded && !empty($url)) {
                    $formattedContents[] = [
                        'id' => $urlItem['id'] ?? uniqid('url_'),
                        'title' => $urlItem['title'] ?? 'No title',
                        'url' => $url,
                        'description' => $urlItem['summary'] ?? $urlItem['description'] ?? '',
                        'notes' => $urlItem['notes'] ?? '',
                        'type' => 'url',
                        'relevance_score' => $urlItem['relevance_score'] ?? 0.5,
                        'created_at' => $urlItem['created_at'] ?? now()->toIso8601String()
                    ];
                }
            }
            
            // Sort all contents by relevance score
            usort($formattedContents, function($a, $b) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            });
            
            return response()->json([
                'library' => $library,
                'contents' => $formattedContents
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error retrieving library: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving library: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update the specified library.
     */
    public function update(Request $request, $id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);
        
        try {
            if ($request->has('name')) {
                $library->name = $request->name;
            }
            
            if ($request->has('description')) {
                $library->description = $request->description;
            }
            
            $library->save();
            
            return response()->json([
                'message' => 'Library updated successfully',
                'library' => $library
            ]);
            
        } catch (\Exception $e) {
            Log::error('Library update failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Library update failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove the specified library.
     */
    public function destroy($id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        try {
            // Delete all content associations first
            DB::table('library_content')
                ->where('library_id', $library->id)
                ->delete();
                
            // Delete the library
            $library->delete();
            
            return response()->json([
                'message' => 'Library deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Library deletion failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Library deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Refresh the content in an auto-generated library.
     */
    public function refreshLibrary($id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        if ($library->type !== 'auto') {
            return response()->json([
                'message' => 'Only auto-generated libraries can be refreshed'
            ], 400);
        }
        
        try {
            $success = $this->libraryService->refreshLibraryContent($library);
            
            if ($success) {
                return response()->json([
                    'message' => 'Library content refreshed successfully',
                    'library' => $library->fresh()
                ]);
            }
            
            return response()->json([
                'message' => 'Failed to refresh library content'
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Library refresh failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Library refresh failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add content to a library manually.
     */
    public function addContent(Request $request, $id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        $request->validate([
            'content_id' => 'required|integer',
            'content_type' => 'required|in:course,post',
            'relevance_score' => 'nullable|numeric|min:0|max:1',
        ]);
        
        try {
            $contentId = $request->content_id;
            $contentType = $request->content_type === 'course' 
                ? Course::class 
                : Post::class;
                
            // Check if content exists
            $content = $contentType::findOrFail($contentId);
            
            // Content moderation check
            $contentModerationService = app(\App\Services\ContentModerationService::class);
            
            // Check content title
            $titleCheck = $contentModerationService->analyzeText($content->title ?? '');
            if (!$titleCheck['isAllowed']) {
                return response()->json([
                    'message' => 'Content contains inappropriate material and cannot be added to the library',
                    'reason' => $titleCheck['reason']
                ], 400);
            }
            
            // Check content body/description
            $bodyCheck = $contentModerationService->analyzeText($content->body ?? $content->description ?? '');
            if (!$bodyCheck['isAllowed']) {
                return response()->json([
                    'message' => 'Content contains inappropriate material and cannot be added to the library',
                    'reason' => $bodyCheck['reason']
                ], 400);
            }
            
            // Check if content is already in library
            $existing = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_id', $contentId)
                ->where('content_type', $contentType)
                ->first();
                
            if ($existing) {
                return response()->json([
                    'message' => 'Content already exists in this library'
                ], 400);
            }
            
            // Add to library
            DB::table('library_content')->insert([
                'library_id' => $library->id,
                'content_id' => $contentId,
                'content_type' => $contentType,
                'relevance_score' => $request->input('relevance_score', 0.5),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return response()->json([
                'message' => 'Content added to library successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Adding content to library failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Adding content to library failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add a URL to a library with automatic content fetching and summarization.
     * Stores URLs inline with other content types.
     */
    public function addUrl(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'notes' => 'nullable|string|max:1000',
            'relevance_score' => 'nullable|numeric|min:0|max:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Find the library
            $library = OpenLibrary::findOrFail($id);
            
            // Fetch and summarize the URL content
            $url = $request->url;
            $urlData = $this->urlFetchService->fetchAndSummarize($url);
            
            if (!$urlData['success']) {
                throw new \Exception('Failed to fetch URL content: ' . ($urlData['error'] ?? 'Unknown error'));
            }
            
            // Skip content moderation for admin users
            $user = Auth::user();
            $isAdmin = $user && $user->isAdmin;
            
            if (!$isAdmin) {
                // Content moderation check for URL content
                $contentModerationService = app(\App\Services\ContentModerationService::class);
                
                // Check URL itself for inappropriate content
                $urlCheck = $contentModerationService->analyzeText($url);
                if (!$urlCheck['isAllowed']) {
                    return response()->json([
                        'message' => 'URL contains inappropriate content and cannot be added to the library',
                        'reason' => $urlCheck['reason']
                    ], 400);
                }
                
                // Check fetched title
                $titleCheck = $contentModerationService->analyzeText($urlData['title'] ?? '');
                if (!$titleCheck['isAllowed']) {
                    return response()->json([
                        'message' => 'URL content contains inappropriate material and cannot be added to the library',
                        'reason' => $titleCheck['reason']
                    ], 400);
                }
                
                // Check fetched summary
                $summaryCheck = $contentModerationService->analyzeText($urlData['summary'] ?? '');
                if (!$summaryCheck['isAllowed']) {
                    return response()->json([
                        'message' => 'URL content contains inappropriate material and cannot be added to the library',
                        'reason' => $summaryCheck['reason']
                    ], 400);
                }
            }
            
            // First, check if this URL already exists in our database
            $existingUrl = LibraryUrl::where('url', $url)->first();
            
            if (!$existingUrl) {
                // Create a new LibraryUrl record
                $existingUrl = LibraryUrl::create([
                    'url' => $url,
                    'title' => $urlData['title'] ?? 'No title',
                    'summary' => $urlData['summary'] ?? 'No summary available',
                    'notes' => $request->notes,
                    'created_by' => Auth::id()
                ]);
            }
            
            // Check if this URL is already in the library
            $existing = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_id', $existingUrl->id)
                ->where('content_type', LibraryUrl::class)
                ->first();
                
            if ($existing) {
                return response()->json([
                    'message' => 'URL already exists in this library',
                    'item' => $existingUrl
                ], 400);
            }
            
            // Add to library content table with the appropriate relevance score
            DB::table('library_content')->insert([
                'library_id' => $library->id,
                'content_id' => $existingUrl->id,
                'content_type' => LibraryUrl::class,
                'relevance_score' => $request->input('relevance_score', 0.8),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // For backward compatibility, also maintain the url_items field
            // This can be removed in a future version after all clients are updated
            $urlItem = [
                'id' => 'url_' . $existingUrl->id,
                'url' => $url,
                'title' => $urlData['title'] ?? 'No title',
                'summary' => $urlData['summary'] ?? 'No summary available',
                'notes' => $request->notes,
                'relevance_score' => $request->input('relevance_score', 0.8),
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String()
            ];
            
            // Get current URL items or initialize empty array
            $urlItems = $library->url_items ?? [];
            
            // Add new URL item
            $urlItems[] = $urlItem;
            
            // Update the library with the new URL item (for backward compatibility)
            $library->url_items = $urlItems;
            $library->save();
            
            return response()->json([
                'message' => 'URL added to library successfully',
                'item' => [
                    'id' => $existingUrl->id,
                    'url' => $existingUrl->url,
                    'title' => $existingUrl->title,
                    'summary' => $existingUrl->summary,
                    'notes' => $existingUrl->notes,
                    'relevance_score' => $request->input('relevance_score', 0.8),
                    'created_at' => $existingUrl->created_at,
                    'updated_at' => $existingUrl->updated_at,
                    'type' => 'url'
                ]
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Adding URL to library failed: ' . $e->getMessage(), [
                'url' => $request->url,
                'library_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to add URL to library: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove a URL from a library.
     */
    public function removeUrl(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'url_id' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Find the library
            $library = OpenLibrary::findOrFail($id);
            $urlId = $request->url_id;
            
            // Handle both formats - numeric IDs (new) and string IDs with url_ prefix (old)
            $numericId = $urlId;
            if (strpos($urlId, 'url_') === 0) {
                $numericId = substr($urlId, 4); // Remove 'url_' prefix
            }
            
            // Remove from library_content table
            $deleted = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_id', $numericId)
                ->where('content_type', LibraryUrl::class)
                ->delete();
            
            // For backward compatibility, also remove from url_items array
            $urlItems = $library->url_items ?? [];
            $foundInLegacy = false;
            
            // Find the URL item by ID in the legacy storage
            $urlItemIndex = null;
            foreach ($urlItems as $index => $item) {
                if (isset($item['id']) && ($item['id'] === $urlId || $item['id'] === 'url_' . $numericId)) {
                    $urlItemIndex = $index;
                    $foundInLegacy = true;
                    break;
                }
            }
            
            // If found in legacy storage, remove it
            if ($foundInLegacy && $urlItemIndex !== null) {
                array_splice($urlItems, $urlItemIndex, 1);
                $library->url_items = $urlItems;
                $library->save();
            }
            
            // If we didn't delete anything from either storage system
            if (!$deleted && !$foundInLegacy) {
                return response()->json([
                    'message' => 'URL not found in this library'
                ], 404);
            }
            
            return response()->json([
                'message' => 'URL removed from library successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Removing URL from library failed: ' . $e->getMessage(), [
                'url_id' => $request->url_id,
                'library_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to remove URL from library: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove content from a library.
     */
    public function removeContent(Request $request, $id)
    {
        $library = OpenLibrary::findOrFail($id);
        
        $request->validate([
            'content_id' => 'required|integer',
            'content_type' => 'required|in:course,post',
        ]);
        
        try {
            $contentId = $request->content_id;
            $contentType = $request->content_type === 'course' 
                ? Course::class 
                : Post::class;
                
            // Remove from library
            $deleted = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_id', $contentId)
                ->where('content_type', $contentType)
                ->delete();
                
            if ($deleted) {
                return response()->json([
                    'message' => 'Content removed from library successfully'
                ]);
            }
            
            return response()->json([
                'message' => 'Content not found in this library'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Removing content from library failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Removing content from library failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
}