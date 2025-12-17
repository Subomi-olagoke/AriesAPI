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
}
