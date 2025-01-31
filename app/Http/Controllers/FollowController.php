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

	public function unFollow(User $user) {
        $deleted = Follow::where(['user_id', '=', auth()->user()->id],
        ['followeduser', '=', $user->id])->delete();

        if($deleted) {
            return back()->with('success', 'User successfully unfollowed');
        }
        return back()->with('failure', 'You are not following this user');
	}

    public function followerCount(User $user) {
        return $user->followers()->count();
    }

    public function followingCount(User $user) {
        return $user->following()->count();
    }
}
