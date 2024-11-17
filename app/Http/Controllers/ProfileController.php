<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller {

	public function viewProfile(User $user) {
        $user = User::with('profile')->find($user->id);

        $posts = $user->posts()->get();
        $likes = '';
        $followers = '';
        $following = '';
        $avatar = $user->profile ? $user->profile->avatar : null;

        return response()->json([
            'posts' => $posts,
            'username' => $user->username,
            'avatar' => $avatar,
        ]);
    }

	public function update(Request $request) {
		$user = Auth::user();
		$profile = Profile::where('user_id', $user->id)->first();

		if (!$profile) {
			$profile = new Profile(['user_id' => $user->id]);
		}

		$profile->fill($request->all());
		$profile->save();

		return response()->json(['profile' => $profile]);
	}

	public function UploadAvatar(Request $request) {
		$user = auth()->user();

		$this->validate($request, [
			'avatar' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
		]);

		if ($request->hasFile('avatar')) {
			$avatar = $request->file('avatar');

			$filename = 'avatar_' . time() . '.' . $avatar->getClientOriginalExtension();

			Storage::disk('s3')->put('avatars/' . $filename, file_get_contents($avatar));

			$user->avatar = $filename;
			$user->save();

			return response()->json(['message' => 'Avatar uploaded and saved successfully']);
		}

		return response()->json(['message' => 'No avatar provided'], 422);

	}

}

