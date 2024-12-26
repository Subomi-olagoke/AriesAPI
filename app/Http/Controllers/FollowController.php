<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\User;
use App\Notifications\followedNotification;

class FollowController extends Controller {
	public function createFollow(User $user) {
		// cannot follow self
        if($user->id == auth()->user()->id) {
            return back()->with('failure', 'You cannot follow yourself');

        }
		//cannot follow already followed user
        $existCheck = $this->followStat($user);
        if($existCheck) {
            return back()->with('failure', 'You are already following this user');
        }

		$newFollow = new Follow();
		$newFollow->user_id = auth()->user()->id;
		$newFollow->followeduser = $user->id;
		$save = $newFollow->save();

        if($save) {
            $notifiable = User::find($newFollow->followeduser);
            $notifiable->notify(new followedNotification(auth()->user()));

            return response()->json([
                'message' => 'followed successfully'
            ], 201);
        }
	}

    public function followStat(User $user) {
        return Follow::where([['user_id', '=', auth()->user()->id],
        ['followeduser', '=', $user->id]])->count();
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
