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
}
