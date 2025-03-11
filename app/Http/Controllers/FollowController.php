<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Follow;
use Illuminate\Http\Request;
use App\Notifications\followedNotification;

class FollowController extends Controller {

    public function createFollow(Request $request, $username) {
        $user = $request->user();

        // Prevent self-following by comparing usernames.
        if ($user->username === $username) {
            return response()->json([
                "message" => "You cannot follow yourself"
            ], 403);
        }

        // Find the target user by username.
        $user2 = User::where('username', $username)->first();
        if (!$user2) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if the authenticated user is already following the target user.
        $existCheck = $this->followStat($user2->id);
        if ($existCheck) {
            return response()->json([
                "message" => "You are already following this user"
            ], 409);
        }

        // Create the follow record using the target user's id.
        $newFollow = new Follow();
        $newFollow->user_id = $user->id;
        $newFollow->followeduser = $user2->id;
        $save = $newFollow->save();

        if ($save) {
            $user2->notify(new followedNotification($user, $user2));
            return response()->json([
                'message' => 'Followed successfully'
            ], 201);
        }
    }

    public function unFollow(Request $request, $username) {
        $user = $request->user();

        // Prevent self-unfollowing (or a no-op) by comparing usernames.
        if ($user->username === $username) {
            return response()->json([
                "message" => "You cannot unfollow yourself"
            ], 403);
        }

        // Find the target user by username.
        $user2 = User::where('username', $username)->first();
        if (!$user2) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if the follow relationship exists.
        $existCheck = $this->followStat($user2->id);
        if (!$existCheck) {
            return response()->json([
                "message" => "You are not following this user"
            ], 403);
        }

        // Delete the follow record.
        $deleted = Follow::where('user_id', $user->id)
            ->where('followeduser', $user2->id)
            ->delete();

        if ($deleted >= 1) {
            return response()->json([
                "message" => "Unfollowed user"
            ], 200);
        }
        return response()->json([
            "message" => "Error. Try again later"
        ], 500);
    }

    // This helper method accepts a user id (of the target user) and checks the follow status.
    public function followStat($targetUserId) {
        return Follow::where([
            ['user_id', '=', auth()->user()->id],
            ['followeduser', '=', $targetUserId]
        ])->exists();
    }

    public function followerCount(User $user) {
        return $user->followers()->count();
    }

    public function followingCount(User $user) {
        return $user->following()->count();
    }
}