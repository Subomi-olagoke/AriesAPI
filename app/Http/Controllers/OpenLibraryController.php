<?php

namespace App\Http\Controllers;

use App\Models\OpenLibrary;
use App\Models\LibraryUrl;
use App\Models\Vote;
use App\Models\Post;
use App\Models\Course;
use App\Models\Comment;
use App\Services\OpenLibraryService;
use App\Services\UrlFetchService;
use App\Services\AlexPointsService;
use App\Services\AiLibraryCategorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class OpenLibraryController extends Controller
{
    protected $libraryService;
    protected $urlFetchService;
    protected $alexPointsService;
    protected $aiCategorizer;
    
    /**
     * Clear cache for a library
     * Note: This clears cache for the library data itself
     * User-specific cache will expire naturally (5 min TTL)
     */
    protected function clearLibraryCache($libraryId)
    {
        // For now, we rely on TTL expiration since we can't easily clear all user variations
        // In production with Redis, you could use cache tags: Cache::tags(["library_{$libraryId}"])->flush();
        // For now, the 5-minute TTL ensures fresh data after updates
    }
    
    /**
     * Create a new controller instance.
     */
    public function __construct(OpenLibraryService $libraryService, UrlFetchService $urlFetchService, AlexPointsService $alexPointsService, AiLibraryCategorizer $aiCategorizer)
    {
        $this->libraryService = $libraryService;
        $this->urlFetchService = $urlFetchService;
        $this->alexPointsService = $alexPointsService;
        $this->aiCategorizer = $aiCategorizer;
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
                ->whereNull('deleted_at') // Exclude deleted libraries
                ->orderBy('created_at', 'desc')
                ->get();
            
            Log::info("User {$userId} created libraries count: " . $createdLibraries->count());
            
            // Get library IDs where user has contributed URLs
            // We need to join library_content with library_urls to find contributions
            $contributedLibraryIds = DB::table('library_content')
                ->join('library_urls', function($join) {
                    $join->on('library_content.content_id', '=', 'library_urls.id')
                         ->where('library_content.content_type', '=', 'url');
                })
                ->where('library_urls.created_by', $userId)
                ->distinct()
                ->pluck('library_content.library_id')
                ->toArray();
            
            Log::info("User {$userId} contributed library IDs: " . json_encode($contributedLibraryIds));
            
            // Get contributed libraries (excluding ones already created by user)
            // Only query if there are contributed library IDs
            $contributedLibraries = collect();
            if (!empty($contributedLibraryIds)) {
                $contributedLibraries = OpenLibrary::whereIn('id', $contributedLibraryIds)
                    ->whereNull('deleted_at') // Exclude deleted libraries
                    ->where(function($query) use ($userId) {
                        $query->whereNull('creator_id')
                            ->orWhere('creator_id', '!=', $userId);
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
            
            Log::info("User {$userId} contributed libraries count: " . $contributedLibraries->count());
            
            // Merge and format the libraries - ensure we only return user's libraries
            $allLibraries = $createdLibraries->merge($contributedLibraries)->unique('id');
            
            Log::info("User {$userId} total libraries (created + contributed): " . $allLibraries->count());
            
            // OPTIMIZED: Batch load all counts to avoid N+1 queries
            $libraryIds = $allLibraries->pluck('id')->toArray();
            
            // Get all content counts in one query
            $contentCounts = DB::table('library_content')
                ->whereIn('library_id', $libraryIds)
                ->select('library_id', DB::raw('count(*) as count'))
                ->groupBy('library_id')
                ->pluck('count', 'library_id')
                ->toArray();
            
            // Get all user contribution counts in one query
            $contributionCounts = [];
            if ($userId) {
                $contributions = DB::table('library_content')
                    ->join('library_urls', function($join) {
                        $join->on('library_content.content_id', '=', 'library_urls.id')
                             ->where('library_content.content_type', '=', LibraryUrl::class);
                    })
                    ->whereIn('library_content.library_id', $libraryIds)
                    ->where('library_urls.created_by', $userId)
                    ->select('library_content.library_id', DB::raw('count(*) as count'))
                    ->groupBy('library_content.library_id')
                    ->get();
                
                foreach ($contributions as $contribution) {
                    $contributionCounts[$contribution->library_id] = $contribution->count;
                }
            }
            
            $formattedLibraries = $allLibraries->map(function ($library) use ($userId, $contentCounts, $contributionCounts) {
                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'type' => $library->type,
                    'thumbnail_url' => $library->thumbnail_url,
                    'cover_image_url' => $library->cover_image_url,
                    'thumbnailUrl' => $library->thumbnail_url, // camelCase for iOS compatibility
                    'coverImageUrl' => $library->cover_image_url, // camelCase for iOS compatibility
                    'isCreator' => $library->creator_id === $userId,
                    'contributionCount' => $contributionCounts[$library->id] ?? 0,
                    'contentCount' => $contentCounts[$library->id] ?? 0,
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
     * Get separate entries (URLs) added by the user.
     */
    public function getUserEntries()
    {
        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $entries = LibraryUrl::where('added_by', $userId)
                ->with(['library' => function($q) {
                    $q->select('id', 'name', 'thumbnail_url');
                }])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'url' => $entry->url,
                        'title' => $entry->title,
                        'summary' => $entry->summary,
                        'notes' => $entry->notes,
                        'type' => $entry->type,
                        'createdAt' => $entry->created_at,
                        'library' => $entry->library ? [
                            'id' => $entry->library->id,
                            'name' => $entry->library->name,
                            'thumbnailUrl' => $entry->library->thumbnail_url
                        ] : null
                    ];
                });
            
            return response()->json([
                'success' => true,
                'entries' => $entries
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching user entries: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user entries: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get user's favorite libraries based on interaction frequency and recency.
     */
    public function getFavorites()
    {
        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            
            // Get libraries the user has contributed URLs to via library_content
            // Join library_content with library_urls to find user's URL contributions
            $urlContributions = DB::table('library_content')
                ->join('library_urls', function($join) {
                    $join->on('library_content.content_id', '=', 'library_urls.id')
                         ->where('library_content.content_type', '=', 'url');
                })
                ->select('library_content.library_id', DB::raw('COUNT(*) as url_count'), DB::raw('MAX(library_content.created_at) as last_interaction'))
                ->where('library_urls.created_by', $userId)
                ->groupBy('library_content.library_id');
            
            // Get libraries the user follows
            $followedLibraries = DB::table('library_follows')
                ->select('library_id', DB::raw('created_at as last_interaction'))
                ->where('user_id', $userId);
            
            // Combine interactions and calculate scores
            $libraryScores = [];
            
            // Process URL contributions
            foreach ($urlContributions->get() as $contribution) {
                $libraryId = $contribution->library_id;
                $urlCount = $contribution->url_count;
                $lastInteraction = $contribution->last_interaction;
                
                // Calculate recency factor (decay over time)
                $daysSinceInteraction = now()->diffInDays($lastInteraction);
                $recencyFactor = 1 / (1 + ($daysSinceInteraction / 30)); // Decay over 30 days
                
                // Score: URL count * 10 + recency bonus
                $score = ($urlCount * 10) + ($recencyFactor * 5);
                
                $libraryScores[$libraryId] = [
                    'score' => $score,
                    'last_interaction' => $lastInteraction
                ];
            }
            
            // Process followed libraries (bonus points)
            foreach ($followedLibraries->get() as $follow) {
                $libraryId = $follow->library_id;
                $lastInteraction = $follow->last_interaction;
                
                if (!isset($libraryScores[$libraryId])) {
                    $libraryScores[$libraryId] = [
                        'score' => 0,
                        'last_interaction' => $lastInteraction
                    ];
                }
                
                // Following bonus: +15 points
                $libraryScores[$libraryId]['score'] += 15;
            }
            
            // Sort by score descending
            uasort($libraryScores, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            // Get top libraries (limit to 15)
            $topLibraryIds = array_slice(array_keys($libraryScores), 0, 15);
            
            if (empty($topLibraryIds)) {
                return response()->json([
                    'success' => true,
                    'libraries' => []
                ]);
            }
            
            // Fetch library details
            $libraries = OpenLibrary::whereIn('id', $topLibraryIds)
                ->with(['contents' => function($query) use ($userId) {
                    $query->limit(3);
                }])
                ->get();
            
            // Check follow status
            $followedLibraryIds = DB::table('library_follows')
                ->where('user_id', $userId)
                ->pluck('library_id')
                ->toArray();
            
            // Format and sort libraries according to score
            $formattedLibraries = collect($topLibraryIds)->map(function($libraryId) use ($libraries, $followedLibraryIds, $userId) {
                $library = $libraries->firstWhere('id', $libraryId);
                
                if (!$library) {
                    return null;
                }
                
                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'type' => $library->type,
                    'thumbnailUrl' => $library->thumbnail_url,
                    'coverImageUrl' => $library->cover_image_url,
                    'courseId' => null,
                    'criteria' => null,
                    'keywords' => null,
                    'isApproved' => $library->is_approved ?? true,
                    'approvalStatus' => $library->approval_status,
                    'viewsCount' => $library->views_count,
                    'isFollowing' => in_array($library->id, $followedLibraryIds),
                    'followersCount' => $library->followers_count,
                    'createdAt' => $library->created_at,
                    'updatedAt' => $library->updated_at,
                    'contents' => $library->contents ? $library->contents->map(function ($content) use ($userId) {
                        return [
                            'id' => $content->id,
                            'title' => $content->title,
                            'url' => $content->url,
                            'summary' => $content->summary,
                            'notes' => $content->notes,
                            'type' => $content->type,
                            'createdAt' => $content->created_at,
                            'content' => null
                        ];
                    })->toArray() : [],
                    'user' => null,
                    'userId' => null
                ];
            })->filter()->values();
            
            return response()->json([
                'success' => true,
                'libraries' => $formattedLibraries
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching favorite libraries: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching favorite libraries: ' . $e->getMessage()
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
            
            // Get follow status for current user if authenticated
            $followedLibraryIds = [];
            if ($userId) {
                $followedLibraryIds = DB::table('library_follows')
                    ->where('user_id', $userId)
                    ->pluck('library_id')
                    ->toArray();
            }
            
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
            
            // ========== CURATED SECTIONS MAPPING ==========
            // Ordered as per user preference
            $curatedSections = [
                // 1. Technology & AI
                'Technology & AI' => ['github', 'artificial intelligence', 'ai bubble', 'ai utilities', 'ai products', 'learning ai', 'machine learning', 'software engineering', 'product design', 'cybersecurity', 'quantum studies', 'quantum', 'chips', 'microprocessors', 'hugging face', 'nvidia'],
                
                // 2. Science and Environment
                'Science and Environment' => ['biology', 'microbiology', 'neuroscience', 'human anatomy', 'anatomy', 'dna', 'genomics', 'veterinary', 'animal science', 'clinical medicine', 'surgery', 'cancer', 'autism', 'medical conditions', 'medical research', 'pharmacology', 'pharmacy', 'nursing', 'dentistry', 'dermatology', 'skin care', 'mental health', 'psychology', 'astronomy', 'astrophysics', 'oceanography', 'geology', 'meteorology', 'climate', 'natural disasters', 'disasters', 'space', 'rocket', 'solar engineering', 'aviation', 'wildlife', 'nature'],
                
                // 3. History and Society
                'History and Society' => ['ww1', 'ww2', 'world war', 'cold war', 'europe', 'european politics', 'european culture', 'asia', 'asian politics', 'uk politics', 'middle east', 'migration', 'immigration', 'global conflicts', 'conflicts', 'global finance', 'economics', 'economies', 'entrepreneurship', 'business', 'management', 'ngo', 'legal', 'law', 'black culture', 'anthropology', 'theology', 'philosophy', 'journalism', 'languages'],
                
                // 4. Arts, Crafts, Media & Creativity
                'Arts, Crafts, Media & Creativity' => ['graphic design', 'painting', 'art', 'filmmaking', 'photography', 'vfx', 'visual effects', 'ballet', 'classical music', 'music', 'poetry', 'creativity', 'fashion', 'make up', 'makeup', 'shoes', 'sneakers', 'watches', 'home decors', 'decors', 'jewelleries', 'jewelry', 'flowers', 'floristry', 'cakes', 'baking', 'pastry', 'pottery', 'fiber', 'textiles', 'crocheting', 'knitting', 'wood', 'woodworks'],
                
                // 5. Travel and Food
                'Travel and Food' => ['travels', 'tourism', 'mountain', 'extreme sports', 'polo', 'cars', 'automobiles', 'global cuisines', 'cuisines', 'european food', 'asian cuisines', 'cakes', 'baking', 'pastry'],
                
                // 6. Hobbies & Crafts
                'Hobbies & Crafts' => ['crocheting', 'knitting', 'pottery', 'fiber', 'textiles', 'painting', 'art', 'home decors', 'baking', 'pastry', 'gardening', 'floristry', 'disc jockeying', 'dj'],
                
                // 7. Entertainment & Pop Culture
                'Entertainment & Pop Culture' => ['movies', 'cinema', 'anime', 'manga', 'youtube videos', 'youtube', 'disc jockeying', 'dj', 'mobile games', 'games'],
                
                // 8. Sports & Fitness
                'Sports & Fitness' => ['f1', 'formula 1', 'boxing', 'mma', 'basketball', 'tennis', 'extreme sports', 'weight loss', 'fitness'],
                
                // 9. Mysteries & Curiosities
                'Mysteries & Curiosities' => ['unsolved mysteries', 'mysteries', 'astrology', 'it\'s interesting', 'interesting', 'youtube videos', 'youtube'],
                
                // 10. Crypto
                'Crypto' => ['solana', 'base', 'eth', 'ethereum', 'prediction markets', 'crypto', 'blockchain', 'cryptocurrency'],
                
                // 11. Finance & Investment
                'Finance & Investment' => ['global finance', 'quant trading', 'trading', 'stocks', 'investing', 'equity', 'entrepreneurship', 'economics', 'economies', 'startups', 'african startups'],
                
                // 12. Philosophy & Thought
                'Philosophy & Thought' => ['philosophy', 'theology', 'psychology', 'creativity', 'anthropology'],
                
                // 13. Books
                'Books' => ['book review', 'book', 'literature', 'poetry', 'reading'],
                
                // 14. World News and Geopolitics
                'World News and Geopolitics' => ['politics', 'political science', 'uk politics', 'asia', 'asian politics', 'europe', 'european politics', 'global conflicts', 'geopolitics', 'news']
            ];
            
            // Fuzzy matching function
            $matchLibraryToSection = function($libraryName, $keywords) {
                $name = strtolower($libraryName);
                foreach ($keywords as $keyword) {
                    if (stripos($name, strtolower($keyword)) !== false) {
                        return true;
                    }
                }
                return false;
            };
            
            $sections = [];
            $usedLibraryIds = [];
            
            // Add curated sections first
            foreach ($curatedSections as $sectionTitle => $keywords) {
                $matchingLibraries = $allLibraries->filter(function ($lib) use ($matchLibraryToSection, $keywords, $followedLibraryIds) {
                    if (in_array($lib->id, $followedLibraryIds)) return false;
                    return $matchLibraryToSection($lib->name, $keywords);
                })->take(10);
                
                if ($matchingLibraries->count() >= 3) {
                    $formattedLibraries = $matchingLibraries->map($formatLibrary)->values()->toArray();
                    $usedLibraryIds = array_merge($usedLibraryIds, $matchingLibraries->pluck('id')->toArray());
                    
                    $sections[] = [
                        'id' => 'curated_' . strtolower(str_replace([' ', '&'], ['_', 'and'], $sectionTitle)),
                        'title' => $sectionTitle,
                        'type' => 'curated',
                        'source_library_id' => null,
                        'source_library_name' => null,
                        'libraries' => $formattedLibraries
                    ];
                }
            }
            
            
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
            
            
            // ========== SECTION 4: CATEGORIZED DISCOVERY SECTIONS ==========
            // Group libraries by keywords and create topic-based sections
            $exploreLibraries = $allLibraries->filter(function ($lib) use ($usedLibraryIds, $followedLibraryIds, $createdLibraryIds) {
                return !in_array($lib->id, $usedLibraryIds) 
                    && !in_array($lib->id, $followedLibraryIds)
                    && !in_array($lib->id, $createdLibraryIds);
            })->values();
            
            if ($exploreLibraries->isNotEmpty()) {
                // Group libraries by keywords
                $keywordGroups = [];
                foreach ($exploreLibraries as $library) {
                    if (!empty($library->keywords) && is_array($library->keywords)) {
                        foreach ($library->keywords as $keyword) {
                            // Normalize keyword (capitalize first letter, trim)
                            $normalizedKeyword = ucfirst(trim(strtolower($keyword)));
                            if (!isset($keywordGroups[$normalizedKeyword])) {
                                $keywordGroups[$normalizedKeyword] = [];
                            }
                            // Only add if not already in this keyword group
                            if (!in_array($library->id, array_column($keywordGroups[$normalizedKeyword], 'id'))) {
                                $keywordGroups[$normalizedKeyword][] = $library;
                            }
                        }
                    }
                }
                
                // Sort keyword groups by library count (most popular first)
                uasort($keywordGroups, function($a, $b) {
                    return count($b) - count($a);
                });
                
                // Take top 7 keyword groups with at least 3 libraries each
                $topKeywordGroups = array_filter($keywordGroups, function($libs) {
                    return count($libs) >= 3;
                });
                $topKeywordGroups = array_slice($topKeywordGroups, 0, 7, true);
                
                // Section title templates for variety
                $titleTemplates = [
                    'Libraries on %s',
                    'Explore %s',
                    'Dive into %s',
                    'Discover %s',
                    '%s Collections',
                    'You Might Like %s',
                    'Trending in %s'
                ];
                
                $templateIndex = 0;
                $categorizedUsedIds = [];
                
                foreach ($topKeywordGroups as $keyword => $libraries) {
                    // Filter out libraries already used in other categorized sections
                    $availableLibraries = array_filter($libraries, function($lib) use ($categorizedUsedIds) {
                        return !in_array($lib->id, $categorizedUsedIds);
                    });
                    
                    // Skip if not enough libraries left
                    if (count($availableLibraries) < 3) {
                        continue;
                    }
                    
                    // Take up to 10 libraries per section
                    $sectionLibraries = array_slice($availableLibraries, 0, 10);
                    $formattedCategorized = array_map($formatLibrary, $sectionLibraries);
                    
                    // Track used library IDs
                    $categorizedUsedIds = array_merge(
                        $categorizedUsedIds, 
                        array_column($sectionLibraries, 'id')
                    );
                    
                    // Create section with varied title
                    $template = $titleTemplates[$templateIndex % count($titleTemplates)];
                    $sectionTitle = sprintf($template, $keyword);
                    
                    Log::info("Creating category section '{$sectionTitle}' with " . count($formattedCategorized) . " libraries");
                    if (count($formattedCategorized) > 0) {
                        Log::info("First library in '{$sectionTitle}': " . json_encode([
                            'name' => $formattedCategorized[0]['name'],
                            'thumbnailUrl' => $formattedCategorized[0]['thumbnailUrl'] ?? 'NULL',
                            'coverImageUrl' => $formattedCategorized[0]['coverImageUrl'] ?? 'NULL'
                        ]));
                    }
                    
                    $sections[] = [
                        'id' => 'category_' . strtolower(str_replace(' ', '_', $keyword)),
                        'title' => $sectionTitle,
                        'type' => 'discovery',
                        'source_library_id' => null,
                        'source_library_name' => null,
                        'libraries' => array_values($formattedCategorized)
                    ];
                    
                    $templateIndex++;
                }
                
                // If we have leftover libraries that don't fit any popular category,
                // add a generic "More to Explore" section
                $remainingLibraries = $exploreLibraries->filter(function($lib) use ($categorizedUsedIds) {
                    return !in_array($lib->id, $categorizedUsedIds);
                })->values();
                
                Log::info("Remaining libraries for 'More to Explore': " . $remainingLibraries->count());
                
                if ($remainingLibraries->count() >= 1) {
                    $formattedRemaining = $remainingLibraries->take(15)->map($formatLibrary)->values()->toArray();
                    
                    Log::info("Creating 'More to Explore' section with " . count($formattedRemaining) . " libraries");
                    if (count($formattedRemaining) > 0) {
                        Log::info("First library in More to Explore: " . json_encode([
                            'name' => $formattedRemaining[0]['name'],
                            'thumbnailUrl' => $formattedRemaining[0]['thumbnailUrl'],
                            'coverImageUrl' => $formattedRemaining[0]['coverImageUrl']
                        ]));
                    }
                    
                    $sections[] = [
                        'id' => 'more_to_explore',
                        'title' => 'More to Explore',
                        'type' => 'discovery',
                        'source_library_id' => null,
                        'source_library_name' => null,
                        'libraries' => $formattedRemaining
                    ];
                }
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
            
            // Award points for creating a library
            if ($user) {
                try {
                    $this->alexPointsService->addPoints(
                        $user,
                        'create_library',
                        OpenLibrary::class,
                        $library->id,
                        "Created library: {$library->name}"
                    );
                } catch (\Exception $e) {
                    // Log but don't fail the request if points fail
                    Log::warning('Failed to award points for library creation: ' . $e->getMessage());
                }
            }
            
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
                    // Get vote counts
                    $upvotesCount = DB::table('votes')
                        ->where('voteable_type', LibraryUrl::class)
                        ->where('voteable_id', $libraryUrl->id)
                        ->where('vote_type', 'up')
                        ->count();
                    
                    $downvotesCount = DB::table('votes')
                        ->where('voteable_type', LibraryUrl::class)
                        ->where('voteable_id', $libraryUrl->id)
                        ->where('vote_type', 'down')
                        ->count();
                    
                    // Get comment count
                    $commentsCount = DB::table('comments')
                        ->where('commentable_type', LibraryUrl::class)
                        ->where('commentable_id', $libraryUrl->id)
                        ->count();
                    
                    // Check user's vote
                    $userVote = DB::table('votes')
                        ->where('user_id', $user->id)
                        ->where('voteable_type', LibraryUrl::class)
                        ->where('voteable_id', $libraryUrl->id)
                        ->first();
                    
                    // Get like count (for backward compatibility)
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
                        'upvotes_count' => $upvotesCount,
                        'downvotes_count' => $downvotesCount,
                        'comments_count' => $commentsCount,
                        'user_vote' => $userVote ? $userVote->vote_type : null,
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
            $user = Auth::user();
            $userId = $user ? $user->id : null;
            
            // Use cache for library data (cache for 5 minutes, invalidate on update)
            $cacheKey = "library_{$id}_user_{$userId}";
            $cacheTtl = 300; // 5 minutes
            
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                // Still increment view count but don't block response
                dispatch(function() use ($id) {
                    OpenLibrary::where('id', $id)->increment('views_count');
                })->afterResponse();
                
                return response()->json($cachedData);
            }
            
            $library = OpenLibrary::findOrFail($id);
            
            // Increment view count (async to not block)
            $library->increment('views_count');
            
            // Get follow status and followers count in one query
            // Use separate queries to avoid SQL injection and UUID quoting issues
            $followersCount = DB::table('library_follows')
                ->where('library_id', $library->id)
                ->count();
            
            $isFollowing = false;
            if ($userId) {
                $isFollowing = DB::table('library_follows')
                    ->where('library_id', $library->id)
                    ->where('user_id', $userId)
                    ->exists();
            }
            
            // Get content with appropriate relationships - OPTIMIZED to avoid N+1 queries
            $contents = DB::table('library_content')
                ->where('library_id', $library->id)
                ->orderBy('relevance_score', 'desc')
                ->get();
            
            // Group content by type to batch load
            $courseIds = [];
            $postIds = [];
            $urlIds = [];
            
            // Build content relationships map - tracks which content belongs to THIS library
            $contentRelationships = []; // Track which content belongs to this library
            foreach ($contents as $content) {
                $contentType = $content->content_type;
                $contentId = $content->content_id;
                
                // Store the relationship to verify content belongs to THIS library
                // IMPORTANT: notes are stored in library_content, not in the content items themselves
                $contentRelationships[$contentType][$contentId] = [
                    'relevance_score' => $content->relevance_score,
                    'library_content_id' => $content->id, // The library_content table row ID
                    'notes' => $content->notes // Per-library notes from library_content table
                ];
                
                if ($contentType === Course::class) {
                    $courseIds[] = $contentId;
                } elseif ($contentType === Post::class) {
                    $postIds[] = $contentId;
                } elseif ($contentType === LibraryUrl::class) {
                    $urlIds[] = $contentId;
                }
            }
            
            // Batch load all content items with relationships
            // Only load items that are actually in this library's content list
            $courses = !empty($courseIds) ? Course::with('user', 'topic')
                ->whereIn('id', $courseIds)
                ->get()
                ->filter(function($course) use ($contentRelationships) {
                    // Verify this course is actually linked to this library
                    return isset($contentRelationships[Course::class][$course->id]);
                })
                ->keyBy('id') : collect();
                
            $posts = !empty($postIds) ? Post::with('user')
                ->whereIn('id', $postIds)
                ->get()
                ->filter(function($post) use ($contentRelationships) {
                    // Verify this post is actually linked to this library
                    return isset($contentRelationships[Post::class][$post->id]);
                })
                ->keyBy('id') : collect();
                
            $urls = !empty($urlIds) ? LibraryUrl::with('creator')
                ->whereIn('id', $urlIds)
                ->get()
                ->filter(function($url) use ($contentRelationships) {
                    // Verify this URL is actually linked to this library
                    return isset($contentRelationships[LibraryUrl::class][$url->id]);
                })
                ->keyBy('id') : collect();
            
            // Batch load votes and comments for all URLs at once
            $votesData = [];
            $commentsCounts = [];
            $userVotes = [];
            
            if (!empty($urlIds)) {
                // Get all vote counts in one query
                $allVotes = DB::table('votes')
                    ->where('voteable_type', LibraryUrl::class)
                    ->whereIn('voteable_id', $urlIds)
                    ->select('voteable_id', 'vote_type', DB::raw('count(*) as count'))
                    ->groupBy('voteable_id', 'vote_type')
                    ->get();
                
                foreach ($allVotes as $vote) {
                    if (!isset($votesData[$vote->voteable_id])) {
                        $votesData[$vote->voteable_id] = ['up' => 0, 'down' => 0];
                    }
                    $votesData[$vote->voteable_id][$vote->vote_type] = $vote->count;
                }
                
                // Get all comment counts in one query
                $allComments = DB::table('comments')
                    ->where('commentable_type', LibraryUrl::class)
                    ->whereIn('commentable_id', $urlIds)
                    ->select('commentable_id', DB::raw('count(*) as count'))
                    ->groupBy('commentable_id')
                    ->get();
                
                foreach ($allComments as $comment) {
                    $commentsCounts[$comment->commentable_id] = $comment->count;
                }
                
                // Get user's votes in one query
                if ($user) {
                    $userVoteRecords = DB::table('votes')
                        ->where('user_id', $user->id)
                        ->where('voteable_type', LibraryUrl::class)
                        ->whereIn('voteable_id', $urlIds)
                        ->get();
                    
                    foreach ($userVoteRecords as $vote) {
                        $userVotes[$vote->voteable_id] = $vote->vote_type;
                    }
                }
            }
            
            // Format contents
            $formattedContents = [];
            
            // Process courses - only include items verified to be in this library
            foreach ($courses as $course) {
                if (isset($contentRelationships[Course::class][$course->id])) {
                    $formattedContents[] = [
                        'id' => $course->id,
                        'title' => $course->title,
                        'description' => $course->description,
                        'thumbnail_url' => $course->thumbnail_url,
                        'user' => $course->user ? [
                            'id' => $course->user->id,
                            'username' => $course->user->username
                        ] : null,
                        'topic' => $course->topic ? [
                            'id' => $course->topic->id,
                            'name' => $course->topic->name
                        ] : null,
                        'type' => 'course',
                        'relevance_score' => $contentRelationships[Course::class][$course->id]['relevance_score']
                    ];
                }
            }
            
            // Process posts - only include items verified to be in this library
            foreach ($posts as $post) {
                if (isset($contentRelationships[Post::class][$post->id])) {
                    $formattedContents[] = [
                        'id' => $post->id,
                        'title' => $post->title,
                        'body' => substr($post->body, 0, 200) . '...',
                        'media_link' => $post->media_link,
                        'media_type' => $post->media_type,
                        'user' => $post->user ? [
                            'id' => $post->user->id,
                            'username' => $post->user->username
                        ] : null,
                        'type' => 'post',
                        'relevance_score' => $contentRelationships[Post::class][$post->id]['relevance_score']
                    ];
                }
            }
            
            // Process URLs - only include items verified to be in this library
            foreach ($urls as $urlItem) {
                if (isset($contentRelationships[LibraryUrl::class][$urlItem->id])) {
                    $formattedContents[] = [
                        'id' => $urlItem->id,
                        'title' => $urlItem->title,
                        'url' => $urlItem->url,
                        'description' => $urlItem->summary,
                        'notes' => $contentRelationships[LibraryUrl::class][$urlItem->id]['notes'] ?? null, // Per-library notes from library_content
                        'type' => 'url',
                        'relevance_score' => $contentRelationships[LibraryUrl::class][$urlItem->id]['relevance_score'],
                        'created_at' => $urlItem->created_at ? $urlItem->created_at->toIso8601String() : now()->toIso8601String(),
                        'added_by' => $urlItem->creator ? [
                            'id' => $urlItem->creator->id,
                            'username' => $urlItem->creator->username
                        ] : null,
                        'upvotes_count' => $votesData[$urlItem->id]['up'] ?? 0,
                        'downvotes_count' => $votesData[$urlItem->id]['down'] ?? 0,
                        'comments_count' => $commentsCounts[$urlItem->id] ?? 0,
                        'user_vote' => $userVotes[$urlItem->id] ?? null
                    ];
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
                    // For legacy url_items, we don't have creator info, so set added_by to null
                    $formattedContents[] = [
                        'id' => $urlItem['id'] ?? uniqid('url_'),
                        'title' => $urlItem['title'] ?? 'No title',
                        'url' => $url,
                        'description' => $urlItem['summary'] ?? $urlItem['description'] ?? '',
                        'notes' => $urlItem['notes'] ?? '',
                        'type' => 'url',
                        'relevance_score' => $urlItem['relevance_score'] ?? 0.5,
                        'created_at' => $urlItem['created_at'] ?? now()->toIso8601String(),
                        'added_by' => null // Legacy items don't have creator info
                    ];
                }
            }
            
            // Sort all contents by relevance score
            usort($formattedContents, function($a, $b) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            });
            
            // Refresh library to get updated views_count
            $library->refresh();
            
            $responseData = [
                'library' => array_merge($library->toArray(), [
                    'views_count' => $library->views_count ?? 0,
                    'is_following' => $isFollowing,
                    'followers_count' => $followersCount
                ]),
                'contents' => $formattedContents
            ];
            
            // Cache the response
            Cache::put($cacheKey, $responseData, $cacheTtl);
            
            return response()->json($responseData);
            
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
            
            // Clear cache for this library
            $this->clearLibraryCache($library->id);
            
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
            'title' => 'nullable|string|max:500',
            'summary' => 'nullable|string|max:2000',
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
            
            // Use metadata provided by frontend instead of fetching
            $url = $request->url;
            $title = $request->input('title');
            $summary = $request->input('summary');
            
            // If title/summary not provided, extract from URL as fallback
            if (empty($title)) {
                $parsedUrl = parse_url($url);
                $domain = $parsedUrl['host'] ?? 'Unknown';
                $title = preg_replace('/^www\./', '', $domain);
                $title = ucfirst($title);
            }
            if (empty($summary)) {
                $summary = $url;
            }
            
            // URLs are fine to be added to libraries - no content moderation needed
            // Libraries are curated spaces where admins and users can add educational resources
            
            // First, check if this URL already exists in our database
            $existingUrl = LibraryUrl::where('url', $url)->first();
            
            if (!$existingUrl) {
                // Create a new LibraryUrl record
                $existingUrl = LibraryUrl::create([
                    'url' => $url,
                    'title' => $title,
                    'summary' => $summary,
                    'notes' => $request->notes,
                    'created_by' => Auth::id()
                ]);
            } else {
                // Update existing URL with new metadata if provided
                if ($title && $title !== $existingUrl->title) {
                    $existingUrl->title = $title;
                }
                if ($summary && $summary !== $existingUrl->summary) {
                    $existingUrl->summary = $summary;
                }
                if ($request->notes && $request->notes !== $existingUrl->notes) {
                    $existingUrl->notes = $request->notes;
                }
                $existingUrl->save();
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
            
            // Add to library content table with the appropriate relevance score AND notes
            DB::table('library_content')->insert([
                'library_id' => $library->id,
                'content_id' => $existingUrl->id,
                'content_type' => LibraryUrl::class,
                'relevance_score' => $request->input('relevance_score', 0.8),
                'notes' => $request->notes, // Store per-library notes here
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // For backward compatibility, also maintain the url_items field
            // This can be removed in a future version after all clients are updated
            $urlItem = [
                'id' => 'url_' . $existingUrl->id,
                'url' => $url,
                'title' => $title,
                'summary' => $summary,
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
            
            // Clear cache for this library
            $this->clearLibraryCache($library->id);
            
            // Award points for adding URL to library
            $user = Auth::user();
            if ($user) {
                try {
                    $this->alexPointsService->addPoints(
                        $user,
                        'add_url',
                        LibraryUrl::class,
                        $existingUrl->id,
                        "Added URL to library: {$library->name}"
                    );
                } catch (\Exception $e) {
                    // Log but don't fail the request if points fail
                    Log::warning('Failed to award points for adding URL: ' . $e->getMessage());
                }
            }
            
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
            
            // Clear cache for this library
            $this->clearLibraryCache($id);
            
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
    
    /**
     * Smart add URL - AI automatically categorizes and adds to appropriate library
     */
    public function smartAddUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'title' => 'nullable|string|max:500',
            'summary' => 'nullable|string|max:2000',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $url = $request->input('url');
        $notes = $request->input('notes');
        $title = $request->input('title');
        $summary = $request->input('summary');

        try {
            // Use metadata provided by frontend
            // If not provided, extract from URL as fallback
            if (empty($title)) {
                $parsedUrl = parse_url($url);
                $domain = $parsedUrl['host'] ?? 'Unknown';
                $title = preg_replace('/^www\./', '', $domain);
                $title = ucfirst($title);
            }
            if (empty($summary)) {
                $summary = $url;
            }

            // Step 2: Get all approved libraries for categorization
            $query = OpenLibrary::select([
                'id', 'name', 'description', 'keywords', 'criteria', 'thumbnail_url', 'cover_image_url'
            ])->where('is_approved', true);

            $libraries = $query->whereNull('deleted_at')->get()->map(function ($library) {
                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description ?? '',
                    'keywords' => is_array($library->keywords) ? $library->keywords : [],
                    'criteria' => is_array($library->criteria) ? $library->criteria : []
                ];
            })->toArray();

            if (empty($libraries)) {
                return response()->json([
                    'message' => 'No libraries available for categorization'
                ], 404);
            }

            // Step 3: Use AI to categorize
            $categorizationResult = $this->aiCategorizer->categorize($url, $title, $summary, $libraries);

            if (!isset($categorizationResult['library_id'])) {
                return response()->json([
                    'message' => 'AI categorization failed to determine appropriate library'
                ], 500);
            }

            $selectedLibraryId = $categorizationResult['library_id'];
            $confidence = $categorizationResult['confidence'] ?? 0;
            $reasoning = $categorizationResult['reasoning'] ?? 'No reasoning provided';
            $alternatives = $categorizationResult['alternatives'] ?? [];

            // Step 4: Check if URL already exists
            $existingUrl = LibraryUrl::where('url', $url)->first();

            if (!$existingUrl) {
                // Create new URL entry
                $existingUrl = LibraryUrl::create([
                    'url' => $url,
                    'title' => $title,
                    'summary' => $summary,
                    'notes' => $notes,
                    'submitted_by' => Auth::id()
                ]);
            }

            // Step 5: Check if already in the selected library
            $existing = DB::table('library_content')
                ->where('library_id', $selectedLibraryId)
                ->where('content_id', $existingUrl->id)
                ->where('content_type', LibraryUrl::class)
                ->first();

            if ($existing) {
                // Get library details for response
                $library = OpenLibrary::find($selectedLibraryId);
                
                return response()->json([
                    'message' => 'URL already exists in this library',
                    'selectedLibrary' => [
                        'id' => $library->id,
                        'name' => $library->name,
                        'description' => $library->description,
                        'thumbnailUrl' => $library->thumbnail_url,
                        'coverImageUrl' => $library->cover_image_url
                    ],
                    'confidence' => $confidence,
                    'reasoning' => $reasoning,
                    'item' => $existingUrl
                ], 200);
            }

            // Step 6: Add to the selected library
            DB::table('library_content')->insert([
                'library_id' => $selectedLibraryId,
                'content_id' => $existingUrl->id,
                'content_type' => LibraryUrl::class,
                'relevance_score' => max(0.5, $confidence), // Use AI confidence as relevance score
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Step 7: Award points for adding content
            $user = Auth::user();
            if ($user) {
                $this->alexPointsService->addPoints(
                    $user,
                    'contributed_url',
                    'library_url',
                    $existingUrl->id,
                    "Added URL to library via smart categorization"
                );
            }

            // Step 8: Get the full library details for response
            $library = OpenLibrary::find($selectedLibraryId);

            // Map alternatives to include full library details
            $alternativeLibraries = collect($alternatives)->map(function ($alt) {
                $lib = OpenLibrary::find($alt['library_id']);
                return $lib ? [
                    'library' => [
                        'id' => $lib->id,
                        'name' => $lib->name,
                        'description' => $lib->description,
                        'thumbnailUrl' => $lib->thumbnail_url
                    ],
                    'confidence' => $alt['confidence']
                ] : null;
            })->filter()->values()->toArray();

            return response()->json([
                'message' => 'URL successfully categorized and added',
                'selectedLibrary' => [
                    'id' => $library->id,
                    'name' => $library->name,
                    'description' => $library->description,
                    'thumbnailUrl' => $library->thumbnail_url,
                    'coverImageUrl' => $library->cover_image_url
                ],
                'confidence' => $confidence,
                'reasoning' => $reasoning,
                'alternatives' => $alternativeLibraries,
                'item' => [
                    'id' => $existingUrl->id,
                    'url' => $existingUrl->url,
                    'title' => $existingUrl->title,
                    'summary' => $existingUrl->summary,
                    'notes' => $existingUrl->notes,
                    'relevance_score' => max(0.5, $confidence)
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Smart add URL failed: ' . $e->getMessage(), [
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to categorize and add URL: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Refresh metadata for a specific URL in a library
     */
    public function refreshUrlMetadata(Request $request, $id, $urlId)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:500',
            'summary' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $library = OpenLibrary::findOrFail($id);
            $libraryUrl = \App\Models\LibraryUrl::findOrFail($urlId);
            
            // Verify this URL belongs to this library
            $inLibrary = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_id', $libraryUrl->id)
                ->where('content_type', \App\Models\LibraryUrl::class)
                ->exists();
            
            if (!$inLibrary) {
                return response()->json([
                    'message' => 'URL not found in this library'
                ], 404);
            }
            
            // Clear cache for this URL
            $cacheKey = 'url_metadata_' . md5($libraryUrl->url);
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
            
            // Use metadata provided by frontend, or keep existing if not provided
            if ($request->has('title') && !empty($request->title)) {
                $libraryUrl->title = $request->title;
            }
            if ($request->has('summary') && !empty($request->summary)) {
                $libraryUrl->summary = $request->summary;
            }
            $libraryUrl->save();
            
            return response()->json([
                'message' => 'URL metadata refreshed successfully',
                'url' => [
                    'id' => $libraryUrl->id,
                    'url' => $libraryUrl->url,
                    'title' => $libraryUrl->title,
                    'summary' => $libraryUrl->summary
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Refresh URL metadata failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to refresh URL metadata: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Refresh metadata for all URLs in a library
     */
    public function refreshAllUrlMetadata($id)
    {
        try {
            $library = OpenLibrary::findOrFail($id);
            
            // Get all URLs in this library
            $urls = DB::table('library_content')
                ->where('library_id', $library->id)
                ->where('content_type', \App\Models\LibraryUrl::class)
                ->get();
            
            if ($urls->isEmpty()) {
                return response()->json([
                    'message' => 'No URLs found in this library'
                ], 404);
            }
            
            $updated = 0;
            $failed = 0;
            
            foreach ($urls as $urlRelation) {
                try {
                    $libraryUrl = \App\Models\LibraryUrl::find($urlRelation->content_id);
                    
                    if (!$libraryUrl) {
                        $failed++;
                        continue;
                    }
                    
                    // Clear cache
                    $cacheKey = 'url_metadata_' . md5($libraryUrl->url);
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                    
                    // Note: Metadata should be refreshed individually via refreshUrlMetadata endpoint
                    // which accepts metadata from the frontend. This batch operation just clears cache.
                    // URLs will be updated when frontend calls refreshUrlMetadata with new metadata.
                    $updated++;
                    
                    // Small delay to avoid rate limiting
                    usleep(500000); // 0.5 seconds
                    
                } catch (\Exception $e) {
                    Log::error('Failed to refresh URL: ' . $e->getMessage(), [
                        'url_id' => $urlRelation->content_id
                    ]);
                    $failed++;
                }
            }
            
            return response()->json([
                'message' => 'Metadata refresh completed',
                'updated' => $updated,
                'failed' => $failed,
                'total' => $urls->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Refresh all URL metadata failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to refresh URL metadata: ' . $e->getMessage()
            ], 500);
        }
    }
    
}