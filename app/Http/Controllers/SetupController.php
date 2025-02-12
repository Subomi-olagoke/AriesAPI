<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setup;
use App\Models\Topic;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    public function setup(Request $request) {
            $request->validate([
                'user_id' => ['required', 'exists:users,id'],
                'role' => ['required', 'in:educator,learner'],
                'selected_topic_ids' => 'required|array',
                'selected_topic_ids.*' => 'exists:topics,id',
                'description' => 'nullable|string',
                'qualifications' => 'nullable|string',
                'objectives' => 'nullable|string',
                'social_links' => 'nullable|array',
                'payment_methods' => 'nullable|array',
            ]);

            $user = User::find($request->input('user_id'));
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $user->role = $request->role;
            $user->save();
            $user->topic()->sync($request->input('selected_topic_ids'));

            // Create or update the user's setup details
            Setup::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'description' => $request->description,
                    'qualifications' => $request->qualifications,
                    'objectives' => $request->objectives,
                    'social_links' => json_encode($request->social_links),
                    'payment_methods' => json_encode($request->payment_methods),
                ]
            );

            $topics = Topic::select('id', 'name')->get();

            return response()->json([
                'message' => 'Role and setup details updated successfully.',
                'setup_completed' => $user->setup_completed,
                'user' => $user,
                'preferences' => $user->topic()->pluck('topic_id'),
                'topics' => $topics->map(fn($topic) => [
                    'id' => $topic->id,
                    'name' => $topic->name,
                ]),
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
        $user->topic()->sync($request->input('selected_topic_ids'));

        return response()->json([
            'message' => 'Preferences saved successfully',
            'preferences' => $user->topic()->pluck('id'),
        ], 200);
    }

    public function followOptions(Request $request) {

        $preferredTopicIds = Topic::pluck('id');

        // $educators = User::where('role', User::ROLE_EDUCATOR)
        // ->whereHas('topics', fn($query) => $query->whereIn('id', $preferredTopicIds))
        // ->get();

        $users = User::whereHas('topic', fn($query) => $query->whereIn('topics.id', $preferredTopicIds))
        ->with('topic')
        ->get();

        return response()->json([
            'users' => $users->map(fn($user) => [
                'id' => $user->id,
                'username' => $user->username,
                'bio' => $user->bio ?? '',
                'profile_image' => $user->profile_image ?? '',
                'topics' => $user->topic->map(fn($topic) => $topic->name),
            ]),
        ]);

    }

    public function checkSetupStatus(Request $request) {
        $user = User::find($request->query('user_id'));

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'setup_completed' => $user->setup_completed ?? false,
        ]);
    }

}

