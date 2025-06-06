<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AuthManager;
use App\Http\Controllers\SharedPostController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\WaitlistController;

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

// Admin waitlist view routes
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/waitlist', [WaitlistController::class, 'adminIndex'])->name('admin.waitlist');
    Route::post('/admin/waitlist/send-email', [WaitlistController::class, 'sendEmail'])->name('admin.waitlist.send-email');
});

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
        Route::get('/export-stats', [App\Http\Controllers\AdminAuthController::class, 'exportStats'])->name('admin.export-stats');
        Route::get('/api/dashboard-stats', [App\Http\Controllers\AdminDashboardStatsController::class, 'getStats'])->name('admin.api.dashboard-stats');
        Route::post('/logout', [App\Http\Controllers\AdminAuthController::class, 'logout'])->name('admin.logout');
        
        // Library management routes
        Route::prefix('libraries')->name('admin.libraries.')->group(function () {
            Route::get('/', [App\Http\Controllers\AdminLibraryController::class, 'listLibraries'])->name('index');
            Route::get('/create', [App\Http\Controllers\AdminLibraryController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\AdminLibraryController::class, 'store'])->name('store');
            Route::get('/{id}', [App\Http\Controllers\AdminLibraryController::class, 'viewLibrary'])->name('view');
            Route::post('/{id}/approve', [App\Http\Controllers\AdminLibraryController::class, 'approveLibrary'])->name('approve');
            Route::post('/{id}/reject', [App\Http\Controllers\AdminLibraryController::class, 'rejectLibrary'])->name('reject');
            Route::post('/{id}/generate-cover', [App\Http\Controllers\AdminLibraryController::class, 'regenerateCoverImage'])->name('generate-cover');
            
            // AI Library Generation
            Route::get('/generate', [App\Http\Controllers\AdminLibraryController::class, 'showGenerateLibrariesForm'])->name('generate-form');
            Route::post('/generate', [App\Http\Controllers\AdminLibraryController::class, 'generateLibraries'])->name('generate');
            
            // Content management routes
            Route::get('/{id}/add-content', [App\Http\Controllers\AdminLibraryController::class, 'addContentForm'])->name('add-content-form');
            Route::post('/{id}/add-content', [App\Http\Controllers\AdminLibraryController::class, 'addContent'])->name('add-content');
            Route::post('/{id}/remove-content', [App\Http\Controllers\AdminLibraryController::class, 'removeContent'])->name('remove-content');
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
        Route::get('/content/export', [App\Http\Controllers\AdminController::class, 'exportContentStats'])->name('admin.content.export');
        
        // Reports routes
        Route::get('/reports', [App\Http\Controllers\AdminController::class, 'reportsDashboard'])->name('admin.reports');
        Route::get('/reports/all', [App\Http\Controllers\AdminController::class, 'allReports'])->name('admin.reports.all');
        Route::get('/reports/{id}', [App\Http\Controllers\AdminController::class, 'viewReport'])->name('admin.reports.view');
        Route::put('/reports/{id}/status', [App\Http\Controllers\AdminController::class, 'updateReportStatus'])->name('admin.reports.status');
        
        Route::get('/revenue', [App\Http\Controllers\AdminController::class, 'revenueDashboard'])->name('admin.revenue');
        Route::get('/verifications', [App\Http\Controllers\AdminController::class, 'verificationsDashboard'])->name('admin.verifications');
        Route::get('/settings', [App\Http\Controllers\AdminController::class, 'settingsDashboard'])->name('admin.settings');
    });
});

// Educator Routes
Route::prefix('educator')->group(function () {
    // Guest routes for educators
    Route::middleware('guest')->group(function () {
        Route::get('/login', [App\Http\Controllers\EducatorAuthController::class, 'showLoginForm'])->name('educator.login');
        Route::post('/login', [App\Http\Controllers\EducatorAuthController::class, 'login']);
    });
    
    // Auth protected routes - must be an educator
    Route::middleware(['auth', 'educator', 'not.banned'])->group(function () {
        // Dashboard
        Route::get('/dashboard', [App\Http\Controllers\EducatorDashboardController::class, 'index'])->name('educator.dashboard');
        Route::post('/logout', [App\Http\Controllers\EducatorAuthController::class, 'logout'])->name('educator.logout');
        
        // Courses Management
        Route::prefix('courses')->name('educator.courses.')->group(function () {
            Route::get('/', [App\Http\Controllers\EducatorCoursesController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\EducatorCoursesController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\EducatorCoursesController::class, 'store'])->name('store');
            Route::get('/{id}', [App\Http\Controllers\EducatorCoursesController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [App\Http\Controllers\EducatorCoursesController::class, 'edit'])->name('edit');
            Route::put('/{id}', [App\Http\Controllers\EducatorCoursesController::class, 'update'])->name('update');
            Route::delete('/{id}', [App\Http\Controllers\EducatorCoursesController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/toggle-featured', [App\Http\Controllers\EducatorCoursesController::class, 'toggleFeatured'])->name('toggle-featured');
            
            // Course Sections
            Route::get('/{id}/sections/create', [App\Http\Controllers\EducatorCoursesController::class, 'createSection'])->name('sections.create');
            Route::post('/{id}/sections', [App\Http\Controllers\EducatorCoursesController::class, 'storeSection'])->name('sections.store');
            Route::get('/{courseId}/sections/{sectionId}/edit', [App\Http\Controllers\EducatorCoursesController::class, 'editSection'])->name('sections.edit');
            Route::put('/{courseId}/sections/{sectionId}', [App\Http\Controllers\EducatorCoursesController::class, 'updateSection'])->name('sections.update');
            Route::delete('/{courseId}/sections/{sectionId}', [App\Http\Controllers\EducatorCoursesController::class, 'destroySection'])->name('sections.destroy');
            
            // Course Lessons
            Route::get('/{courseId}/sections/{sectionId}/lessons/create', [App\Http\Controllers\EducatorCoursesController::class, 'createLesson'])->name('lessons.create');
            Route::post('/{courseId}/sections/{sectionId}/lessons', [App\Http\Controllers\EducatorCoursesController::class, 'storeLesson'])->name('lessons.store');
            Route::get('/{courseId}/lessons/{lessonId}/edit', [App\Http\Controllers\EducatorCoursesController::class, 'editLesson'])->name('lessons.edit');
            Route::put('/{courseId}/lessons/{lessonId}', [App\Http\Controllers\EducatorCoursesController::class, 'updateLesson'])->name('lessons.update');
            Route::delete('/{courseId}/lessons/{lessonId}', [App\Http\Controllers\EducatorCoursesController::class, 'destroyLesson'])->name('lessons.destroy');
        });
        
        // Students Management
        Route::get('/students', [App\Http\Controllers\EducatorDashboardController::class, 'students'])->name('educator.students');
        
        // Earnings
        Route::get('/earnings', [App\Http\Controllers\EducatorDashboardController::class, 'earnings'])->name('educator.earnings');
        
        // Settings
        Route::get('/settings', [App\Http\Controllers\EducatorDashboardController::class, 'settings'])->name('educator.settings');
        Route::post('/settings', [App\Http\Controllers\EducatorDashboardController::class, 'updateSettings'])->name('educator.settings.update');
    });
});

// Payment success/failure pages
Route::view('/payment-methods/success', 'payment-methods.success')->name('payment-methods.success');
Route::view('/payment-methods/failed', 'payment-methods.failed')->name('payment-methods.failed');

// Subscription success/failure pages
Route::view('/subscription/success', 'subscription.success')->name('subscription.success');
Route::view('/subscription/failed', 'subscription.failed')->name('subscription.failed');

// Premium Subscription Storefront Routes
Route::get('/premium', [App\Http\Controllers\PremiumStorefrontController::class, 'index'])->name('premium.storefront');
Route::get('/premium/subscribe', [App\Http\Controllers\PremiumStorefrontController::class, 'subscribe'])->name('premium.subscribe');
Route::get('/premium/success', [App\Http\Controllers\PremiumStorefrontController::class, 'success'])->name('premium.success');
Route::get('/premium/failed', [App\Http\Controllers\PremiumStorefrontController::class, 'failed'])->name('premium.failed');

// Enrollment success/failure pages
Route::view('/enrollment/success', 'enrollment.success')->name('enrollment.success');
Route::view('/enrollment/failed', 'enrollment.failed')->name('enrollment.failed');