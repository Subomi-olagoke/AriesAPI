<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * Search across users, libraries, and readlists
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $query = $request->input('query');
        $results = [];

        // Search Users
        $users = User::where(function($q) use ($query) {
            $q->where('username', 'ILIKE', "%{$query}%")
              ->orWhere('first_name', 'ILIKE', "%{$query}%")
              ->orWhere('last_name', 'ILIKE', "%{$query}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$query}%"]);
        })
        ->where('is_banned', false)
        ->limit(10)
        ->get(['id', 'username', 'first_name', 'last_name', 'avatar', 'role', 'email', 'setup_completed'])
        ->map(function($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $user->avatar,
                'role' => $user->role,
                'email' => $user->email,
                'setup_completed' => $user->setup_completed,
                'type' => 'user'
            ];
        });

        // Search Libraries
        $libraries = DB::table('open_libraries')
            ->where(function($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                  ->orWhere('description', 'ILIKE', "%{$query}%");
            })
            ->whereNull('deleted_at')
            ->where('is_approved', true)
            ->limit(10)
            ->get(['id', 'name', 'description', 'thumbnail_url', 'cover_image_url', 'type'])
            ->map(function($library) {
                return [
                    'id' => (string)$library->id,
                    'title' => $library->name,
                    'body' => $library->description,
                    'thumbnail_url' => $library->thumbnail_url,
                    'cover_image_url' => $library->cover_image_url,
                    'library_type' => $library->type,
                    'type' => 'library'
                ];
            });

        // Search Readlists
        $readlists = DB::table('readlists')
            ->where(function($q) use ($query) {
                $q->where('title', 'ILIKE', "%{$query}%")
                  ->orWhere('description', 'ILIKE', "%{$query}%");
            })
            ->where('is_public', true)
            ->limit(10)
            ->get(['id', 'title', 'description', 'image_url', 'user_id'])
            ->map(function($readlist) {
                return [
                    'id' => (string)$readlist->id,
                    'title' => $readlist->title,
                    'body' => $readlist->description,
                    'image_url' => $readlist->image_url,
                    'user_id' => $readlist->user_id,
                    'type' => 'readlist'
                ];
            });

        // Combine all results
        $results = array_merge(
            $users->toArray(),
            $libraries->toArray(),
            $readlists->toArray()
        );

        return response()->json([
            'success' => true,
            'results' => $results,
            'count' => count($results)
        ]);
    }

    /**
     * Get user's recent searches
     */
    public function getRecentSearches(Request $request)
    {
        $user = $request->user();
        
        // Get recent searches from user_search_history table (or use cache/meta)
        $recentSearches = DB::table('user_search_history')
            ->where('user_id', $user->id)
            ->orderBy('searched_at', 'desc')
            ->limit(10)
            ->pluck('query')
            ->toArray();
        
        return response()->json([
            'success' => true,
            'recent_searches' => $recentSearches
        ]);
    }

    /**
     * Save a search query to user's history
     */
    public function saveRecentSearch(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100'
        ]);

        $user = $request->user();
        $query = $request->input('query');

        // Remove existing entry if it exists
        DB::table('user_search_history')
            ->where('user_id', $user->id)
            ->where('query', $query)
            ->delete();

        // Insert new entry
        DB::table('user_search_history')->insert([
            'user_id' => $user->id,
            'query' => $query,
            'searched_at' => now()
        ]);

        // Keep only last 20 searches per user
        $count = DB::table('user_search_history')
            ->where('user_id', $user->id)
            ->count();

        if ($count > 20) {
            DB::table('user_search_history')
                ->where('user_id', $user->id)
                ->orderBy('searched_at', 'asc')
                ->limit($count - 20)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Search saved'
        ]);
    }

    /**
     * Clear user's search history
     */
    public function clearRecentSearches(Request $request)
    {
        $user = $request->user();
        
        DB::table('user_search_history')
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Search history cleared'
        ]);
    }

    /**
     * Get search suggestions (trending + popular libraries)
     */
    public function getSuggestions(Request $request)
    {
        // Get popular/trending libraries
        $popularLibraries = DB::table('open_libraries')
            ->whereNull('deleted_at')
            ->where('is_approved', true)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'name', 'description', 'thumbnail_url', 'cover_image_url', 'type']);

        // Get trending search terms (most searched in last 7 days)
        $trendingSearches = DB::table('user_search_history')
            ->where('searched_at', '>=', now()->subDays(7))
            ->select('query', DB::raw('COUNT(*) as count'))
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit(8)
            ->pluck('query')
            ->toArray();

        // If no trending searches, use defaults
        if (empty($trendingSearches)) {
            $trendingSearches = ['Design', 'Technology', 'Business', 'Science', 'Arts', 'Health'];
        }

        return response()->json([
            'success' => true,
            'trending_searches' => $trendingSearches,
            'popular_libraries' => $popularLibraries
        ]);
    }

    /**
     * Get suggested libraries for search view
     * Returns personalized library suggestions based on user's preferences
     */
    public function getSuggestedLibraries(Request $request)
    {
        try {
            $user = $request->user();
            $userId = $user ? $user->id : null;

            // Get all approved libraries
            $hasIsApprovedColumn = \Illuminate\Support\Facades\Schema::hasColumn('open_libraries', 'is_approved');
            $hasApprovalStatusColumn = \Illuminate\Support\Facades\Schema::hasColumn('open_libraries', 'approval_status');
            
            $query = DB::table('open_libraries')
                ->whereNull('deleted_at');
            
            if ($hasIsApprovedColumn && $hasApprovalStatusColumn) {
                $query->where(function($q) {
                    $q->where('is_approved', true)
                      ->orWhere('approval_status', 'approved')
                      ->orWhere(function($nullQ) {
                          $nullQ->whereNull('is_approved')
                                ->whereNull('approval_status');
                      });
                })->where(function($q) {
                    $q->whereNull('approval_status')
                      ->orWhere('approval_status', '!=', 'rejected');
                });
            } elseif ($hasIsApprovedColumn) {
                $query->where(function($q) {
                    $q->where('is_approved', true)
                      ->orWhereNull('is_approved');
                });
            } elseif ($hasApprovalStatusColumn) {
                $query->where(function($q) {
                    $q->where('approval_status', 'approved')
                      ->orWhere(function($nullQ) {
                          $nullQ->whereNull('approval_status')
                                ->orWhere('approval_status', '!=', 'rejected');
                      });
                });
            }

            // Get user's followed library IDs if authenticated
            $followedLibraryIds = [];
            if ($userId) {
                $followedLibraryIds = DB::table('library_follows')
                    ->where('user_id', $userId)
                    ->pluck('library_id')
                    ->toArray();
            }

            // Get libraries with most followers (popular libraries)
            $popularLibraryIds = DB::table('library_follows')
                ->select('library_id', DB::raw('COUNT(*) as follower_count'))
                ->groupBy('library_id')
                ->orderByDesc('follower_count')
                ->limit(20)
                ->pluck('library_id')
                ->toArray();

            // Get suggested libraries (exclude followed ones, prioritize popular ones)
            $suggestedQuery = $query->whereNotIn('id', $followedLibraryIds);
            
            // Prioritize popular libraries if we have any
            if (!empty($popularLibraryIds)) {
                $suggestedQuery->orderByRaw('CASE WHEN id IN (' . implode(',', array_map('intval', $popularLibraryIds)) . ') THEN 0 ELSE 1 END');
            }
            
            $suggestedLibraries = $suggestedQuery
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'name', 'description', 'thumbnail_url', 'cover_image_url', 'type', 'keywords'])
                ->map(function($library) {
                    return [
                        'id' => (int)$library->id,
                        'name' => $library->name,
                        'description' => $library->description,
                        'thumbnail_url' => $library->thumbnail_url,
                        'cover_image_url' => $library->cover_image_url,
                        'type' => $library->type,
                        'keywords' => $library->keywords ? json_decode($library->keywords, true) : []
                    ];
                });

            return response()->json([
                'success' => true,
                'libraries' => $suggestedLibraries
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get suggested libraries failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggested libraries: ' . $e->getMessage(),
                'libraries' => []
            ], 500);
        }
    }
}
