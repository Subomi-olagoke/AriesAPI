<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\FileUploadService;

class ProfileController extends Controller {

    public function viewProfile(User $user) {
        $user = User::with('profile')->find($user->id);
    
        // Get posts ordered by newest first
        $posts = $user->posts()
                      ->orderBy('created_at', 'desc')
                      ->get();
                      
        $likes = $user->likes()->get();
        $followers = $user->followers()->count();
        $following = $user->following()->count();
        
        // Prioritize the direct avatar attribute
        $avatar = $user->avatar ?? 
                  ($user->profile && $user->profile->avatar ? $user->profile->avatar : null);
        
        // Construct full name from first_name and last_name
        $fullName = $user->first_name . ' ' . $user->last_name;
        
        // Get the bio from the profile
        $bio = $user->profile ? $user->profile->bio : null;
    
        return response()->json([
            'posts' => $posts,
            'username' => $user->username,
            'full_name' => $fullName,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => $avatar,
            'bio' => $bio,
            'followers' => $followers,
            'following' => $following,
            'likes' => $likes
        ]);
    }

    public function update(Request $request) {
        $user = Auth::user();
        $profile = Profile::where('user_id', $user->id)->first();

        if (!$profile) {
            // Create a new profile if one doesn't exist
            $profile = new Profile();
            $profile->user_id = $user->id;
        }

        // Validate the request
        $request->validate([
            'bio' => 'nullable|string|max:500'  // Add appropriate validation rules
        ]);

        // Update profile fields
        if ($request->has('bio')) {
            $profile->bio = $request->bio;
        }
        
        // Fill other profile fields if they exist in the request
        $profile->fill($request->except('bio'));
        
        $profile->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profile
        ]);
    }

    public function UploadAvatar(Request $request) {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:3000'
        ]);
    
        $user = auth()->user();
        
        try {
            $fileUploadService = app(FileUploadService::class);
            
            // Upload with image processing
            $avatarUrl = $fileUploadService->uploadFile(
                $request->file('avatar'), 
                'avatars',
                [
                    'process_image' => true,
                    'width' => 120,
                    'height' => 120,
                    'fit' => true
                ]
            );
            
            if (!$avatarUrl) {
                return response()->json([
                    'message' => 'Failed to upload avatar to Cloudinary'
                ], 500);
            }
            
            // Store previous avatar for deletion
            $oldAvatar = $user->avatar;
            
            // Update user record with new avatar URL
            $user->avatar = $avatarUrl;
            $user->save();
            
            // Optionally update profile avatar as well if you want consistency
            $profile = $user->profile ?? new Profile();
            $profile->user_id = $user->id;
            $profile->avatar = $avatarUrl;
            $profile->save();
            
            // Delete old avatar if it exists and isn't the default
            if ($oldAvatar && $oldAvatar != "/fallback-avatar.jpg" && !str_contains($oldAvatar, 'fallback')) {
                $fileUploadService->deleteFile($oldAvatar);
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