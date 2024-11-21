<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Topic;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    public function setup(Request $request) {
        $request->validate([
            'role' => ['required', 'in:educator,learner'],
        ]);
        $user = auth()->user();
        $user->save();

        return response()->json([
            'message' => 'Role updated successfully.',
            'user' => $user,
        ]);
    }

    public function Preferences(Request $request){
        $topics =  $topics = Topic::all();

        return response()->json([
            'topics' => $topics->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'name' => $topic->name,
                ];
            }),
        ]);

    }

    public function savePreferences(Request $request) {

        $request->validate([
            'selected_topic_ids' => 'required|array',
            'selected_topic_ids.*' => 'exists:topics,id'
        ]);

        $request->validate([
            'selected_topic_ids' => 'required|array',
        ]);

        $user = $request->user();
        $user->topics()->sync($request->input('selected_topic_ids'));

        return response()->json([
            'message' => 'Preferences saved successfully',
        ], 200);
    }

}

