<?php

namespace App\Http\Controllers;

//use App\Models\Access\User\User;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller {

	public function profile(Request $request) {
		$user = $request->User();
		if ($user) {
			return response()->json($user, 200);
		} else {
			return response()->json([
				'message' => 'user not found',
			], 404);
		}
	}

	public function alphaProfile(User $user) {
		$data = [
			'username' => $user->username,
			'posts' => $user->posts()->latest()->get(),
			'postCount' => $user->posts()->count(),

		];

		return response()->json(['data' => $data]);
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

/*public function showProfile(User $user) {
//$user = Auth::user();
$profile = Profile::where('user_id', $user->id)->first();
return response()->json(['profile' => $profile]);
}*/
