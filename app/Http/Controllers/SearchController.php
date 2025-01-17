<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Post;
use Illuminate\Http\Request;
use Workbench\App\Models\User;

class SearchController extends Controller
{
    public function search(Request $request) {
        $request->validate([
            'query' => 'required|string|max:255',
            'type' => 'nullable|in:post,user,course',
        ]);


        $query = $request->input('query');
        $type = $request->input('type');

        if ($type && !in_array($type, ['post', 'user', 'course'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid search type provided.',
            ], 400);
        }

        // Search based on type
        $results = match ($type) {
            'post' => Post::search($query)->get(),
            'user' => User::search($query)->get(),
            'course' => Course::search($query)->get(),
            default => [
                'posts' => Post::search($query)->get(),
                'users' => User::search($query)->get(),
                'courses' => Course::search($query)->get(),
            ],
        };

        if ($results->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No results found for your query.',
                'results' => [],
            ]);
        }

        //Return the search results
        return response()->json([
            'success' => true,
            'results' => $results,
        ]);

    }
}
