<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AuthManager;
use App\Http\Controllers\SharedPostController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ChannelController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/



Route::get('/', function () {
    return view('welcome');
});



// Add this to your routes/web.php file
Route::get('/posts/shared/{shareKey}', [PostController::class, 'viewSharedPost'])
    ->name('posts.shared')
    ->withoutMiddleware(['auth:sanctum']);

// Channel deep linking route
Route::get('/channel/{id}', function($id) {
    // Fetch channel data to display details
    $channel = \App\Models\Channel::with(['creator', 'members.user'])->find($id);
    
    // For web, redirect to the app or web version as needed
    $userAgent = request()->header('User-Agent');
    $isMobile = str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'Android');
    
    if ($isMobile) {
        // Show a landing page with deep link, app store buttons
        return view('channel.deep-link', [
            'channelId' => $id,
            'channel' => $channel
        ]);
    } else {
        // Web app version with channel details
        return view('channel.view', [
            'channelId' => $id,
            'channel' => $channel
        ]);
    }
})->name('channel.deep-link');

// Post deep linking route
Route::get('/post/{id}', function($id) {
    // Fetch post data to display details
    $post = \App\Models\Post::with(['user'])->find($id);
    
    // For web, redirect to the app or web version as needed
    $userAgent = request()->header('User-Agent');
    $isMobile = str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'Android');
    
    // Get like and comment counts
    $likesCount = 0;
    $commentsCount = 0;
    
    if ($post) {
        $likesCount = \App\Models\Like::where('post_id', $post->id)->count();
        $commentsCount = \App\Models\Comment::where('post_id', $post->id)->count();
        
        // Add counts to post object
        $post->likes_count = $likesCount;
        $post->comments_count = $commentsCount;
    }
    
    // Show a landing page with deep link, app store buttons
    return view('post.deep-link', [
        'postId' => $id,
        'post' => $post
    ]);
})->name('post.deep-link');

// Profile deep linking route
Route::get('/profile/{username}', function($username) {
    // Fetch profile data to display details
    $profile = \App\Models\User::with(['profile'])->where('username', $username)->first();
    
    if (!$profile) {
        // Try to find by ID if username not found (for numeric usernames or IDs)
        $profile = \App\Models\User::with(['profile'])->find($username);
    }
    
    // Get follower and following counts
    $followersCount = 0;
    $followingCount = 0;
    $postsCount = 0;
    
    if ($profile) {
        $followersCount = \App\Models\Follow::where('followed_id', $profile->id)->count();
        $followingCount = \App\Models\Follow::where('follower_id', $profile->id)->count();
        $postsCount = \App\Models\Post::where('user_id', $profile->id)->count();
        
        // Combine user and profile data
        $profileData = $profile->toArray();
        if ($profile->profile) {
            $profileData = array_merge($profileData, $profile->profile->toArray());
        }
        
        // Add counts
        $profileData['followers_count'] = $followersCount;
        $profileData['following_count'] = $followingCount;
        $profileData['posts_count'] = $postsCount;
        
        // Convert to object
        $profile = (object)$profileData;
    }
    
    // Show a landing page with deep link, app store buttons
    return view('profile.deep-link', [
        'profileId' => $username,
        'profile' => $profile
    ]);
})->name('profile.deep-link');

// Readlist deep linking route
Route::get('/readlist/{id}', function($id) {
    // Try to find by share key first
    $readlist = \App\Models\Readlist::with(['user', 'items'])->where('share_key', $id)->first();
    
    // If not found by share key, try to find by ID
    if (!$readlist) {
        $readlist = \App\Models\Readlist::with(['user', 'items'])->find($id);
    }
    
    // Show a landing page with deep link, app store buttons
    return view('readlist.deep-link', [
        'readlistId' => $id,
        'readlist' => $readlist
    ]);
})->name('readlist.deep-link');

// Apple App Site Association routes
Route::get('/apple-app-site-association', function() {
    return response()->file(
        public_path('apple-app-site-association'),
        ['Content-Type' => 'application/json']
    );
});

// .well-known alternative location
Route::get('/.well-known/apple-app-site-association', function() {
    return response()->file(
        public_path('.well-known/apple-app-site-association'),
        ['Content-Type' => 'application/json']
    );
});

// Auth routes
Route::get('/login', [AuthManager::class, 'login'])->name('login');
Route::get('/register', [AuthManager::class, 'register'])->name('register');
Route::get('/forgot-password', [AuthManager::class, 'forgotPassword'])->name('forgot-password');
Route::get('/reset-password', [AuthManager::class, 'resetPassword'])->name('reset-password');

// Email verification routes
Route::prefix('email')->group(function () {
    Route::get('/verify', [AuthManager::class, 'verifyEmail'])->name('verification.notice');
    Route::get('/verify/{id}/{hash}', [AuthManager::class, '__invoke'])->middleware(['signed'])->name('verification.verify');
    Route::post('/verification-notification', [AuthManager::class, 'resendVerificationEmail'])->middleware(['throttle:6,1'])->name('verification.send');
});

// Admin Routes
Route::prefix('admin')->group(function () {
    // Guest routes
    Route::middleware('guest')->group(function () {
        Route::get('/login', [App\Http\Controllers\AdminAuthController::class, 'showLoginForm'])->name('admin.login');
        Route::post('/login', [App\Http\Controllers\AdminAuthController::class, 'login']);
    });
    
    // Auth protected routes
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\AdminAuthController::class, 'dashboard'])->name('admin.dashboard');
        Route::post('/logout', [App\Http\Controllers\AdminAuthController::class, 'logout'])->name('admin.logout');
        
        // Library management routes
        Route::prefix('libraries')->name('admin.libraries.')->group(function () {
            Route::get('/', [App\Http\Controllers\AdminLibraryController::class, 'index'])->name('index');
            Route::get('/{id}', [App\Http\Controllers\AdminLibraryController::class, 'show'])->name('view');
            Route::post('/{id}/approve', [App\Http\Controllers\AdminLibraryController::class, 'approve'])->name('approve');
            Route::post('/{id}/reject', [App\Http\Controllers\AdminLibraryController::class, 'reject'])->name('reject');
            Route::post('/{id}/generate-cover', [App\Http\Controllers\AdminLibraryController::class, 'generateCover'])->name('generate-cover');
        });
        
        // User management routes
        Route::prefix('users')->name('admin.users.')->group(function () {
            Route::get('/', [App\Http\Controllers\AdminUserController::class, 'index'])->name('index');
            Route::get('/banned', [App\Http\Controllers\AdminUserController::class, 'bannedUsers'])->name('banned');
            Route::get('/{id}', [App\Http\Controllers\AdminUserController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [App\Http\Controllers\AdminUserController::class, 'edit'])->name('edit');
            Route::put('/{id}', [App\Http\Controllers\AdminUserController::class, 'update'])->name('update');
            Route::post('/{id}/ban', [App\Http\Controllers\AdminUserController::class, 'ban'])->name('ban');
            Route::post('/{id}/unban', [App\Http\Controllers\AdminUserController::class, 'unban'])->name('unban');
            Route::post('/{id}/make-admin', [App\Http\Controllers\AdminUserController::class, 'makeAdmin'])->name('make-admin');
            Route::post('/{id}/remove-admin', [App\Http\Controllers\AdminUserController::class, 'removeAdmin'])->name('remove-admin');
            Route::post('/{id}/notify', [App\Http\Controllers\AdminUserController::class, 'notify'])->name('notify');
            Route::post('/export', [App\Http\Controllers\AdminUserController::class, 'export'])->name('export');
        });
        
        // Other admin routes
        Route::get('/content', [App\Http\Controllers\AdminController::class, 'contentDashboard'])->name('admin.content');
        Route::get('/reports', [App\Http\Controllers\AdminController::class, 'reportsDashboard'])->name('admin.reports');
        Route::get('/revenue', [App\Http\Controllers\AdminController::class, 'revenueDashboard'])->name('admin.revenue');
        Route::get('/verifications', [App\Http\Controllers\AdminController::class, 'verificationsDashboard'])->name('admin.verifications');
        Route::get('/settings', [App\Http\Controllers\AdminController::class, 'settingsDashboard'])->name('admin.settings');
    });
});

// Payment success/failure pages
Route::view('/payment-methods/success', 'payment-methods.success')->name('payment-methods.success');
Route::view('/payment-methods/failed', 'payment-methods.failed')->name('payment-methods.failed');

// Subscription success/failure pages
Route::view('/subscription/success', 'subscription.success')->name('subscription.success');
Route::view('/subscription/failed', 'subscription.failed')->name('subscription.failed');

// Enrollment success/failure pages
Route::view('/enrollment/success', 'enrollment.success')->name('enrollment.success');
Route::view('/enrollment/failed', 'enrollment.failed')->name('enrollment.failed');

