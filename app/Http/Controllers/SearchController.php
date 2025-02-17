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

        $posts = Post::search($query)->get()->load('user');
        $users = User::search($query)->get();
        $courses = Course::search($query)->get()->load('user');

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
