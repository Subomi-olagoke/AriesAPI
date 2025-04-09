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
    
    // Load the user with profile relationship for faster retrieval
    $user->load('profile');
    
    // Get user's posts (public or appropriate visibility level)
    $posts = $user->posts()
        ->with(['user', 'likes', 'comments'])
        ->orderBy('created_at', 'desc')
        ->limit(20)  // More posts for user's own profile
        ->get();
        
    // Map posts to match ProfilePost structure expected by iOS
    $formattedPosts = $posts->map(function($post) {
        return [
            'id' => $post->id,
            'created_at' => $post->created_at->toISOString(),
            'updated_at' => $post->updated_at->toISOString(),
            'title' => $post->title,
            'body' => $post->body,
            'media_link' => $post->media_link,
            'visibility' => $post->visibility,
            'media_type' => $post->media_type ?? 'text',
            'media_thumbnail' => $post->media_thumbnail,
            'user_id' => $post->user_id
        ];
    });
        
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
            'social_links' => $user->profile->social_links ?? [],
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
    
    // IMPORTANT: Return directly at the root level, not nested in another object
    return response()->json([
        'posts' => $formattedPosts,
        'username' => $user->username,
        'full_name' => $user->first_name . ' ' . $user->last_name,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'avatar' => $user->avatar ?? ($user->profile ? $user->profile->avatar : null),
        'bio' => $user->profile ? $user->profile->bio : null,
        'followers' => $followersCount,
        'following' => $followingCount,
        'likes' => $likedPostIds,
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
            $profile->avatar = $avatarUrl;
            
            if ($profile->save()) {
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
            ->with(['user', 'likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->limit(10)  // Limit to recent posts
            ->get();
            
        // Get counts for various metrics
        $followers = $user->followers()->count();
        $following = $user->following()->count();
        $postsCount = $user->posts()->count();
        
        if (!$profile) {
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'role' => $user->role,
                    'avatar' => $user->avatar,
                ],
                'profile' => null,
                'posts' => $posts,
                'counts' => [
                    'followers' => $followers,
                    'following' => $following,
                    'posts' => $postsCount
                ],
                'message' => 'User has no profile'
            ], 200);
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
            'posts' => $posts,
            'counts' => [
                'followers' => $followers,
                'following' => $following,
                'posts' => $postsCount
            ],
            'share_url' => $profile->share_url
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
            ->with(['user', 'likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->limit(10)  // Limit to recent posts to avoid large responses
            ->get();
            
        // Get counts for various metrics
        $followers = $user->followers()->count();
        $following = $user->following()->count();
        $postsCount = $user->posts()->count();
            
        if (!$profile) {
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'role' => $user->role,
                    'avatar' => $user->avatar,
                ],
                'profile' => null,
                'posts' => $posts,
                'counts' => [
                    'followers' => $followers,
                    'following' => $following,
                    'posts' => $postsCount
                ],
                'message' => 'User has no profile'
            ], 200);
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
            'posts' => $posts,
            'counts' => [
                'followers' => $followers,
                'following' => $following,
                'posts' => $postsCount
            ],
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