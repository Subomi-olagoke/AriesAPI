<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

class ProfileController extends Controller {

	public function viewProfile(User $user) {
        $user = User::with('profile')->find($user->id);

        $posts = $user->posts()->get();
        $likes = $user->likes()->get();
        $followers = $user->followers()->count();
        $following = $user->following()->count();
        $avatar = $user->profile ? $user->profile->avatar : null;

        return response()->json([
            'posts' => $posts,
            'username' => $user->username,
            'avatar' => $avatar,
            'followers' => $followers,
            'following' => $following,
            'likes' => $likes
        ]);
    }

	public function update(Request $request) {
		$user = Auth::user();
		$profile = Profile::where('user_id', $user->id)->first();

		if (!$profile) {
			//$profile = new Profile(['user_id' => $user->id]);
            return response()->json([
                'message' => 'you are not allowed to do that'
            ], 403);
		}

		$profile->fill($request->all());
		$profile->save();

		return response()->json(['profile' => $profile]);
	}

	public function UploadAvatar(Request $request) {
        $request->validate([
            'avatar' => 'required|image|max:3000'
           ]);

           $user = auth()->user();

           $filename = $user->id . '_' . uniqid() . '.jpg';

         $imgData = Image::read($request->file('avatar'));
         $imgData->resize(120,120);
         $jpegEncoder = new JpegEncoder();
         $encodedImage = $imgData->encode($jpegEncoder);


         Storage::put('public/avatars/' . $filename, (string) $encodedImage);

         $oldAvatar = $user->avatar;


         $user->avatar = $filename;
         $user->save();

         if($oldAvatar != "/fallback-avatar.jpg") {
            Storage::delete(str_replace("/storage/", "public/", $oldAvatar));
         }

         return response()->json([
            'message' => 'avatar changed successfully'
         ], 200);

	}

}

