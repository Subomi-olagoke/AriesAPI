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
        
        // Construct full name from first_name and last_name
        $fullName = $user->first_name . ' ' . $user->last_name;
    
        return response()->json([
            'posts' => $posts,
            'username' => $user->username,
            'full_name' => $fullName, // Add full name to the response
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
            'avatar' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:3000'
        ]);

        $user = auth()->user();
        
        // Generate filename with original extension to preserve format
        $extension = $request->file('avatar')->getClientOriginalExtension();
        $filename = $user->id . '_' . uniqid() . '.' . $extension;
        
        try {
            $imgData = Image::read($request->file('avatar'));
            
            // Smart crop to maintain aspect ratio and create a square image
            $imgData->fit(120, 120);
            
            // Choose appropriate handling based on file type
            if (in_array(strtolower($extension), ['png', 'gif', 'webp'])) {
                // For formats supporting transparency
                $imgData->save(storage_path('app/public/avatars/' . $filename));
            } else {
                // Use JPEG for other formats
                $jpegEncoder = new JpegEncoder(90); // 90% quality
                $encodedImage = $imgData->encode($jpegEncoder);
                Storage::put('public/avatars/' . $filename, (string) $encodedImage);
            }
            
            // Store previous avatar for deletion
            $oldAvatar = $user->avatar;
            
            // Store the public path in the database
            $user->avatar = '/storage/avatars/' . $filename;
            $user->save();
            
            // Delete old avatar if it exists and isn't the default
            if ($oldAvatar && $oldAvatar != "/fallback-avatar.jpg") {
                $oldPath = str_replace('/storage/', 'public/', $oldAvatar);
                if (Storage::exists($oldPath)) {
                    Storage::delete($oldPath);
                }
            }
            
            return response()->json([
                'message' => 'Avatar updated successfully',
                'avatar' => $user->avatar
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}