<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Models\User;

class SearchController extends Controller
{
    public function search(Request $request) {
        $request->validate([
            'query' => 'required|string|max:255',
        ]);


        $query = $request->input('query');


        // Search based on type
        $results = [
                'posts' => Post::search($query)->get(),
                'users' => User::search($query)->get(),
                'courses' => Course::search($query)->get(),
        ];

        if (empty($results['posts']) && empty($results['users']) && empty($results['courses'])) {
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
