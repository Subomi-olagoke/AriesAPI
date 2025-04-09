<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\FileUploadService;

class ProfileController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function store(Request $request)
    {
        // Validate request
        $request->validate([
            'bio' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'qualifications' => 'nullable|array',
            'teaching_style' => 'nullable|string',
            'availability' => 'nullable|array',
            'hire_rate' => 'nullable|numeric',
            'hire_currency' => 'nullable|string|max:3',
            'social_links' => 'nullable|array'
        ]);

        $user = Auth::user();

        // Check if user already has a profile
        $profile = Profile::where('user_id', $user->id)->first();
        
        if ($profile) {
            return response()->json([
                'message' => 'Profile already exists',
                'profile' => $profile,
                'share_url' => $profile->share_url
            ], 409);
        }

        // Create new profile
        $profile = new Profile();
        $profile->user_id = $user->id;
        $profile->bio = $request->bio;
        $profile->qualifications = $request->qualifications;
        $profile->teaching_style = $request->teaching_style;
        $profile->availability = $request->availability;
        $profile->hire_rate = $request->hire_rate;
        $profile->hire_currency = $request->hire_currency;
        $profile->social_links = $request->social_links;

        // Handle avatar upload if present
        if ($request->hasFile('avatar')) {
            $avatarUrl = $this->fileUploadService->uploadImage($request->file('avatar'), 'avatars');
            $profile->avatar = $avatarUrl;
        }

        if ($profile->save()) {
            return response()->json([
                'message' => 'Profile created successfully',
                'profile' => $profile,
                'share_url' => $profile->share_url
            ], 201);
        }

        return response()->json([
            'message' => 'Error creating profile'
        ], 500);
    }

    public function update(Request $request)
    {
        // Validate request
        $request->validate([
            'bio' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'qualifications' => 'nullable|array',
            'teaching_style' => 'nullable|string',
            'availability' => 'nullable|array',
            'hire_rate' => 'nullable|numeric',
            'hire_currency' => 'nullable|string|max:3',
            'social_links' => 'nullable|array'
        ]);

        $user = Auth::user();

        // Get user's profile
        $profile = Profile::where('user_id', $user->id)->first();
        
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }

        // Update profile
        if ($request->has('bio')) $profile->bio = $request->bio;
        if ($request->has('qualifications')) $profile->qualifications = $request->qualifications;
        if ($request->has('teaching_style')) $profile->teaching_style = $request->teaching_style;
        if ($request->has('availability')) $profile->availability = $request->availability;
        if ($request->has('hire_rate')) $profile->hire_rate = $request->hire_rate;
        if ($request->has('hire_currency')) $profile->hire_currency = $request->hire_currency;
        if ($request->has('social_links')) $profile->social_links = $request->social_links;

        // Handle avatar upload if present
        if ($request->hasFile('avatar')) {
            $avatarUrl = $this->fileUploadService->uploadImage($request->file('avatar'), 'avatars');
            $profile->avatar = $avatarUrl;
        }

        if ($profile->save()) {
            return response()->json([
                'message' => 'Profile updated successfully',
                'profile' => $profile,
                'share_url' => $profile->share_url
            ], 200);
        }

        return response()->json([
            'message' => 'Error updating profile'
        ], 500);
    }

    public function show()
    {
        $user = Auth::user();
        $profile = Profile::where('user_id', $user->id)->first();
        
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }

        return response()->json([
            'profile' => $profile,
            'share_url' => $profile->share_url
        ], 200);
    }
    
    /**
     * Show public profile by user ID
     */
    public function showByUserId($userId)
    {
        $user = User::findOrFail($userId);
        $profile = Profile::where('user_id', $user->id)->first();
        
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }
        
        // Check if requesting user is blocked by profile owner
        $requestingUser = Auth::user();
        if ($requestingUser && $user->hasBlocked($requestingUser)) {
            return response()->json([
                'message' => 'You cannot view this profile'
            ], 403);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $user->role,
                'avatar' => $user->avatar,
            ],
            'profile' => $profile,
            'share_url' => $profile->share_url
        ], 200);
    }
    
    /**
     * View a shared profile using share key
     */
    public function showByShareKey($shareKey)
    {
        $profile = Profile::where('share_key', $shareKey)->first();
        
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }
        
        $user = $profile->user;
        
        // Check if requesting user is blocked by profile owner
        $requestingUser = Auth::user();
        if ($requestingUser && $user->hasBlocked($requestingUser)) {
            return response()->json([
                'message' => 'You cannot view this profile'
            ], 403);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $user->role,
                'avatar' => $user->avatar,
            ],
            'profile' => $profile
        ], 200);
    }
    
    /**
     * Regenerate share key for profile
     */
    public function regenerateShareKey()
    {
        $user = Auth::user();
        $profile = Profile::where('user_id', $user->id)->first();
        
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }
        
        $profile->regenerateShareKey();
        
        return response()->json([
            'message' => 'Share key regenerated successfully',
            'share_key' => $profile->share_key,
            'share_url' => $profile->share_url
        ], 200);
    }
}