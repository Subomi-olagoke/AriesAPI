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

        $results = Post::search($query)->get()
            ->merge(User::search($query)->get())
            ->merge(Course::search($query)->get());

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
