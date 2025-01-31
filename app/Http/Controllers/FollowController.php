<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Follow;
use Illuminate\Http\Request;
use App\Notifications\followedNotification;

class FollowController extends Controller {
	public function createFollow(Request $request, $id) {

        $user = $request->user();
        if($id == $user->id) {
            return response()->json([
                "message" => "you cannot follow yourself"
            ], 403);
        }


        $followedUser = User::find($id);
        if (!$followedUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $existCheck = $this->followStat($id);
        if($existCheck) {
            return response()->json([
                "message" => "You are already following this user"
            ], 409);
        }


		$newFollow = new Follow();
		$newFollow->user_id = $user->id;
		$newFollow->followeduser = $id;
		$save = $newFollow->save();

        if($save) {
            $notifiable = User::find($newFollow->followeduser);
            $notifiable->notify(new followedNotification(auth()->user()));

            return response()->json([
                'message' => 'followed successfully'
            ], 201);
        }
	}

    public function followStat($id) {
        return Follow::where([['user_id', '=', auth()->user()->id],
        ['followeduser', '=', $id]])->exists();
    }

	public function unFollow($id) {

        $existCheck = $this->followStat($id);
        if($existCheck) {
        $deleted = Follow::where('user_id', auth()->user()->id)
        ->where('followeduser', $id)
        ->delete();
        }

        if($deleted) {
            return response()->json([
                "message" => "unfollowed user"
            ], 200);
        }
        return response()->json([
            "message" => "error. try again later, or you are not following this user"
        ], 500);
	}

    public function followerCount(User $user) {
        return $user->followers()->count();
    }

    public function followingCount(User $user) {
        return $user->following()->count();
    }
}
