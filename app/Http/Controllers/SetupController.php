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
            'selected_topic_ids' => 'required|array',
            'selected_topic_ids.*' => 'exists:topics,id'
        ]);
        $user = auth()->user();
        $topics = Topic::select('id', 'name')->get();
        //$topics = Topic::all();

        $user->topics()->sync($request->input('selected_topic_ids'));
        return response()->json([
            'message' => 'Role updated successfully.',
            'user' => $user,
            'preferences' => $user->topics()->pluck('id'),
            'topics' => $topics->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'name' => $topic->name,
                ];
            }),
        ]);
    }

    public function createPreferences(){
        $topics = Topic::all();

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

        $user = $request->auth()->user();
        $user->topics()->sync($request->input('selected_topic_ids'));

        return response()->json([
            'message' => 'Preferences saved successfully',
            'preferences' => $user->topics()->pluck('id'),
        ], 200);
    }

    public function followOptions(Request $request) {
        $user = $request->auth()->user();
        $preferredTopicIds = $user->topics()->pluck('id');

        $educators = User::where('role', User::ROLE_EDUCATOR)
        ->whereHas('topics', fn($query) => $query->whereIn('id', $preferredTopicIds))
        ->get();

        return response()->json([
            'educators' => $educators->map(fn($educator) => [
                'id' => $educator->id,
                'name' => $educator->name,
                'bio' => $educator->bio ?? '',
                'profile_image' => $educator->profile_image ?? '',
            ]),
        ]);

    }

}

