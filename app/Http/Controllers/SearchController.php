<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request) {
        $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $query = $request->input('query');

        // Search users
        $users = User::where('username', 'LIKE', "%{$query}%")
            ->orWhere('first_name', 'LIKE', "%{$query}%")
            ->orWhere('last_name', 'LIKE', "%{$query}%")
            ->get();

        // Search posts
        $posts = Post::where('title', 'LIKE', "%{$query}%")
            ->orWhere('body', 'LIKE', "%{$query}%")
            ->with('user')
            ->get();

        // Search courses
        $courses = Course::where('title', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->with('user')
            ->get();

        $results = $posts->merge($users)->merge($courses);

        if ($results->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No results found for your query.',
                'results' => [],
            ]);
        }

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }
}