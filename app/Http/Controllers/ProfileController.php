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
            $user->avatar = $avatarUrl; // Also update user avatar
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
            'social_links' => 'nullable|array',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255|unique:users,username,' . Auth::id(),
        ]);

        $user = Auth::user();

        // Update user information if provided
        if ($request->has('first_name')) {
            $user->first_name = $request->first_name;
        }
        
        if ($request->has('last_name')) {
            $user->last_name = $request->last_name;
        }
        
        if ($request->has('username')) {
            $user->username = $request->username;
        }
        
        $user->save();

        // Get user's profile
        $profile = Profile::where('user_id', $user->id)->first();
        
        if (!$profile) {
            // Create a new profile if one doesn't exist
            $profile = new Profile();
            $profile->user_id = $user->id;
        }

        // Update profile fields
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
            $user->avatar = $avatarUrl; // Also update user avatar
        }

        if ($profile->save()) {
            return response()->json([
                'message' => 'Profile updated successfully',
                'profile' => $profile,
                'share_url' => $profile->share_url,
                'user' => [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'username' => $user->username,
                ]
            ], 200);
        }

        return response()->json([
            'message' => 'Error updating profile'
        ], 500);
    }

    public function show()
    {
        $user = Auth::user();
        
        // Load the user with profile relationship for faster retrieval
        $user->load('profile');
        
        // Get user's posts (public or appropriate visibility level)
        $posts = $user->posts()
            ->with(['user', 'likes', 'comments', 'media'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        // Get counts for various metrics
        $followersCount = $user->followers()->count();
        $followingCount = $user->following()->count();
        
        // Get likes given by the user (post IDs)
        $likedPostIds = $user->likes()->pluck('post_id')->filter()->map(function($id) {
            return (string)$id;
        })->toArray();
        
        // Prepare educator profile data if user is an educator
        $educatorProfile = null;
        if ($user->role === User::ROLE_EDUCATOR && $user->profile) {
            // Get ratings data
            $ratingsReceived = $user->ratingsReceived()
                ->with('user:id,username,first_name,last_name,avatar')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
                
            $averageRating = $user->ratingsReceived()->avg('rating') ?? 0;
            $ratingsCount = $user->ratingsReceived()->count();
            
            // Format educator profile according to iOS structure
            $educatorProfile = [
                'qualifications' => $user->profile->qualifications ?? [],
                'teaching_style' => $user->profile->teaching_style,
                'availability' => $user->profile->availability ?? [],
                'hire_rate' => (string)($user->profile->hire_rate ?? "0"),
                'hire_currency' => $user->profile->hire_currency ?? 'USD',
                'social_links' => $user->profile->social_links ? (object)$user->profile->social_links : (object)[],
                'average_rating' => (float)$averageRating,
                'ratings_count' => $ratingsCount,
                'recent_ratings' => $ratingsReceived->map(function($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at->toISOString(),
                        'user' => [
                            'username' => $rating->user->username,
                            'first_name' => $rating->user->first_name,
                            'last_name' => $rating->user->last_name,
                            'avatar' => $rating->user->avatar,
                        ]
                    ];
                })->toArray(),
                'description' => $user->profile->bio ?? ''
            ];
        }
        
        // Return directly at the root level, not nested in another object
        return response()->json([
            'username' => $user->username,
            'full_name' => $user->first_name . ' ' . $user->last_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => ($user->profile && $user->profile->avatar) ? $user->profile->avatar : $user->avatar,
            'bio' => $user->profile ? $user->profile->bio : null,
            'followers' => $followersCount,
            'following' => $followingCount,
            'likes' => $likedPostIds,
            'posts' => $posts,
            'educator_profile' => $educatorProfile
        ], 200);
    }
    
    /**
     * Upload avatar image
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);
        
        $user = Auth::user();
        $profile = Profile::where('user_id', $user->id)->first();
        
        if (!$profile) {
            // Create a basic profile if none exists
            $profile = new Profile();
            $profile->user_id = $user->id;
            $profile->save();
        }
        
        if ($request->hasFile('avatar')) {
            $avatarUrl = $this->fileUploadService->uploadImage($request->file('avatar'), 'avatars');
            
            // Update both profile and user avatar fields
            $profile->avatar = $avatarUrl;
            $user->avatar = $avatarUrl;
            
            if ($profile->save() && $user->save()) {
                return response()->json([
                    'message' => 'Avatar uploaded successfully',
                    'avatar_url' => $avatarUrl
                ], 200);
            }
        }
        
        return response()->json([
            'message' => 'Failed to upload avatar'
        ], 500);
    }
    
    /**
     * Update educator profile fields
     */
    public function updateEducatorProfile(Request $request)
    {
        $request->validate([
            'bio' => 'nullable|string',
            'qualifications' => 'nullable|array',
            'teaching_style' => 'nullable|string',
            'availability' => 'nullable|array',
            'hire_rate' => 'nullable|numeric',
            'hire_currency' => 'nullable|string|max:3',
            'social_links' => 'nullable|array'
        ]);
        
        $user = Auth::user();
        
        // Verify user is an educator
        if ($user->role !== User::ROLE_EDUCATOR) {
            return response()->json([
                'message' => 'Only educators can update these profile fields'
            ], 403);
        }
        
        // Get or create profile
        $profile = Profile::where('user_id', $user->id)->first();
        
        if (!$profile) {
            $profile = new Profile();
            $profile->user_id = $user->id;
        }
        
        // Update fields
        if ($request->has('bio')) $profile->bio = $request->bio;
        if ($request->has('qualifications')) $profile->qualifications = $request->qualifications;
        if ($request->has('teaching_style')) $profile->teaching_style = $request->teaching_style;
        if ($request->has('availability')) $profile->availability = $request->availability;
        if ($request->has('hire_rate')) $profile->hire_rate = $request->hire_rate;
        if ($request->has('hire_currency')) $profile->hire_currency = $request->hire_currency;
        if ($request->has('social_links')) $profile->social_links = $request->social_links;
        
        if ($profile->save()) {
            return response()->json([
                'message' => 'Educator profile updated successfully',
                'profile' => $profile,
                'share_url' => $profile->share_url
            ], 200);
        }
        
        return response()->json([
            'message' => 'Error updating educator profile'
        ], 500);
    }
    
    /**
     * Show public profile by user ID
     */
    public function showByUserId($userId)
    {
        $user = User::findOrFail($userId);
        
        // Check if requesting user is blocked by profile owner
        $requestingUser = Auth::user();
        if ($requestingUser && $user->hasBlocked($requestingUser)) {
            return response()->json([
                'message' => 'You cannot view this profile'
            ], 403);
        }
        
        // Get user's profile
        $profile = Profile::where('user_id', $user->id)->first();
        
        // Get user's posts (public or appropriate visibility level)
        $posts = $user->posts()
            ->with(['user', 'likes', 'comments', 'media'])
            ->orderBy('created_at', 'desc')
            ->limit(10)  // Limit to recent posts
            ->get();
            
        // Get counts for various metrics
        $followers = $user->followers()->count();
        $following = $user->following()->count();
        $postsCount = $user->posts()->count();
        
        // Get likes given by the user (post IDs)
        $likedPostIds = $user->likes()->pluck('post_id')->filter()->map(function($id) {
            return (string)$id;
        })->toArray();
        
        // Prepare educator profile data if user is an educator
        $educatorProfile = null;
        if ($user->role === User::ROLE_EDUCATOR && $profile) {
            // Get ratings data
            $ratingsReceived = $user->ratingsReceived()
                ->with('user:id,username,first_name,last_name,avatar')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
                
            $averageRating = $user->ratingsReceived()->avg('rating') ?? 0;
            $ratingsCount = $user->ratingsReceived()->count();
            
            // Format educator profile according to iOS structure
            $educatorProfile = [
                'qualifications' => $profile->qualifications ?? [],
                'teaching_style' => $profile->teaching_style,
                'availability' => $profile->availability ?? [],
                'hire_rate' => (string)($profile->hire_rate ?? "0"),
                'hire_currency' => $profile->hire_currency ?? 'USD',
                'social_links' => $profile->social_links ?? [],
                'average_rating' => (float)$averageRating,
                'ratings_count' => $ratingsCount,
                'recent_ratings' => $ratingsReceived->map(function($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at->toISOString(),
                        'user' => [
                            'username' => $rating->user->username,
                            'first_name' => $rating->user->first_name,
                            'last_name' => $rating->user->last_name,
                            'avatar' => $rating->user->avatar,
                        ]
                    ];
                })->toArray(),
                'description' => $profile->bio ?? ''
            ];
        }

        // Return in the desired format with properties at root level
        return response()->json([
            'username' => $user->username,
            'full_name' => $user->first_name . ' ' . $user->last_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => ($profile && $profile->avatar) ? $profile->avatar : $user->avatar,
            'bio' => $profile ? $profile->bio : null,
            'followers' => $followers,
            'following' => $following,
            'likes' => $likedPostIds,
            'posts' => $posts,
            'is_verified' => $user->is_verified ?? false,
            'verification_status' => $user->verification_status ?? 'pending',
            'educator_profile' => $educatorProfile
        ], 200);
    }
    
    /**
     * Show public profile by username
     */
    public function showByUsername($username)
    {
        $user = User::where('username', $username)->first();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }
        
        // Check if requesting user is blocked by profile owner
        $requestingUser = Auth::user();
        if ($requestingUser && $user->hasBlocked($requestingUser)) {
            return response()->json([
                'message' => 'You cannot view this profile'
            ], 403);
        }
        
        // Get user's profile
        $profile = Profile::where('user_id', $user->id)->first();
        
        // Get user's posts (public or appropriate visibility level)
        $posts = $user->posts()
            ->with(['user', 'likes', 'comments', 'media'])
            ->orderBy('created_at', 'desc')
            ->limit(10)  // Limit to recent posts to avoid large responses
            ->get();
            
        // Get counts for various metrics
        $followers = $user->followers()->count();
        $following = $user->following()->count();
        $postsCount = $user->posts()->count();
        
        // Get likes given by the user (post IDs)
        $likedPostIds = $user->likes()->pluck('post_id')->filter()->map(function($id) {
            return (string)$id;
        })->toArray();
        
        // Prepare educator profile data if user is an educator
        $educatorProfile = null;
        if ($user->role === User::ROLE_EDUCATOR && $profile) {
            // Get ratings data
            $ratingsReceived = $user->ratingsReceived()
                ->with('user:id,username,first_name,last_name,avatar')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
                
            $averageRating = $user->ratingsReceived()->avg('rating') ?? 0;
            $ratingsCount = $user->ratingsReceived()->count();
            
            // Format educator profile according to iOS structure
            $educatorProfile = [
                'qualifications' => $profile->qualifications ?? [],
                'teaching_style' => $profile->teaching_style,
                'availability' => $profile->availability ?? [],
                'hire_rate' => (string)($profile->hire_rate ?? "0"),
                'hire_currency' => $profile->hire_currency ?? 'USD',
                'social_links' => $profile->social_links ?? [],
                'average_rating' => (float)$averageRating,
                'ratings_count' => $ratingsCount,
                'recent_ratings' => $ratingsReceived->map(function($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at->toISOString(),
                        'user' => [
                            'username' => $rating->user->username,
                            'first_name' => $rating->user->first_name,
                            'last_name' => $rating->user->last_name,
                            'avatar' => $rating->user->avatar,
                        ]
                    ];
                })->toArray(),
                'description' => $profile->bio ?? ''
            ];
        }
            
        // Return in the desired format with properties at root level
        return response()->json([
            'username' => $user->username,
            'full_name' => $user->first_name . ' ' . $user->last_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => ($profile && $profile->avatar) ? $profile->avatar : $user->avatar,
            'bio' => $profile ? $profile->bio : null,
            'followers' => $followers,
            'following' => $following,
            'likes' => $likedPostIds,
            'posts' => $posts,
            'is_verified' => $user->is_verified ?? false,
            'verification_status' => $user->verification_status ?? 'pending',
            'educator_profile' => $educatorProfile
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

        // Get user's posts (public or appropriate visibility level)
        $posts = $user->posts()
            ->with(['user', 'likes', 'comments', 'media'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        // Get counts for various metrics
        $followers = $user->followers()->count();
        $following = $user->following()->count();
        
        // Get likes given by the user (post IDs)
        $likedPostIds = $user->likes()->pluck('post_id')->filter()->map(function($id) {
            return (string)$id;
        })->toArray();
        
        // Prepare educator profile data if user is an educator
        $educatorProfile = null;
        if ($user->role === User::ROLE_EDUCATOR) {
            // Get ratings data
            $ratingsReceived = $user->ratingsReceived()
                ->with('user:id,username,first_name,last_name,avatar')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
                
            $averageRating = $user->ratingsReceived()->avg('rating') ?? 0;
            $ratingsCount = $user->ratingsReceived()->count();
            
            // Format educator profile according to iOS structure
            $educatorProfile = [
                'qualifications' => $profile->qualifications ?? [],
                'teaching_style' => $profile->teaching_style,
                'availability' => $profile->availability ?? [],
                'hire_rate' => (string)($profile->hire_rate ?? "0"),
                'hire_currency' => $profile->hire_currency ?? 'USD',
                'social_links' => $profile->social_links ?? [],
                'average_rating' => (float)$averageRating,
                'ratings_count' => $ratingsCount,
                'recent_ratings' => $ratingsReceived->map(function($rating) {
                    return [
                        'id' => $rating->id,
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'created_at' => $rating->created_at->toISOString(),
                        'user' => [
                            'username' => $rating->user->username,
                            'first_name' => $rating->user->first_name,
                            'last_name' => $rating->user->last_name,
                            'avatar' => $rating->user->avatar,
                        ]
                    ];
                })->toArray(),
                'description' => $profile->bio ?? ''
            ];
        }

        // Return in the desired format with properties at root level
        return response()->json([
            'username' => $user->username,
            'full_name' => $user->first_name . ' ' . $user->last_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => ($profile && $profile->avatar) ? $profile->avatar : $user->avatar,
            'bio' => $profile->bio,
            'followers' => $followers,
            'following' => $following,
            'likes' => $likedPostIds,
            'posts' => $posts,
            'is_verified' => $user->is_verified ?? false,
            'verification_status' => $user->verification_status ?? 'pending',
            'educator_profile' => $educatorProfile
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