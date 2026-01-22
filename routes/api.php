<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthManager;
use App\Http\Controllers\ForgotPasswordManager;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpenLibraryController;
use App\Http\Controllers\ReadlistController;
use App\Http\Controllers\LibraryFollowController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\HighlightController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Public routes
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Update user setup completion status
Route::patch('/user/setup-completed', function (Request $request) {
    $user = $request->user();
    $user->setup_completed = $request->input('setup_completed', true);
    $user->save();
    
    return response()->json([
        'message' => 'Setup completion status updated',
        'user' => $user
    ], 200);
})->middleware('auth:sanctum');

// Authentication routes - use stateless middleware to avoid CSRF issues
Route::middleware(['api.stateless'])->group(function () {
    Route::post('/register', [AuthManager::class, 'register']);
    Route::post('/login', [AuthManager::class, 'login']);
    Route::post('/forgot-password', [ForgotPasswordManager::class, 'forgotPassword']);
    Route::post('/reset-password', [ForgotPasswordManager::class, 'resetPassword']);
    Route::post('/reset-password/token', [ResetPasswordController::class, 'generateResetToken']);
    Route::post('/reset-password/reset', [ResetPasswordController::class, 'resetPassword']);
    Route::post('/auth/google', [\App\Http\Controllers\GoogleController::class, 'authenticateWithGoogle']);
    Route::post('/auth/apple', [\App\Http\Controllers\AppleController::class, 'authenticateWithApple']);
});

// Public profile access routes
Route::get('/profile/user/{userId}', [ProfileController::class, 'showByUserId']);
Route::get('/profile/username/{username}', [ProfileController::class, 'showByUsername']);
Route::get('/profile/shared/{shareKey}', [ProfileController::class, 'showByShareKey']);

// Public readlist share route
Route::get('/readlists/shared/{shareKey}', [ReadlistController::class, 'showByShareKey']);
Route::get('/readlist/shared/{shareKey}', [ReadlistController::class, 'showByShareKey']);

// Public library share route
Route::get('/libraries/shared/{shareKey}', [OpenLibraryController::class, 'showByShareKey']);
Route::get('/library/shared/{shareKey}', [OpenLibraryController::class, 'showByShareKey']);

// Protected routes that require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthManager::class, 'logout']);
    
    // Follow options route
    Route::get('/followOptions', [\App\Http\Controllers\FollowOptionsController::class, 'getFollowOptions']);
    
    // Search route
    Route::get('/search', [\App\Http\Controllers\SearchController::class, 'search']);
    
    // Search history and suggestions routes
    Route::get('/search/recent', [\App\Http\Controllers\SearchController::class, 'getRecentSearches']);
    Route::post('/search/recent', [\App\Http\Controllers\SearchController::class, 'saveRecentSearch']);
    Route::delete('/search/recent', [\App\Http\Controllers\SearchController::class, 'clearRecentSearches']);
    Route::get('/search/suggestions', [\App\Http\Controllers\SearchController::class, 'getSuggestions']);
    Route::get('/search/suggested-libraries', [\App\Http\Controllers\SearchController::class, 'getSuggestedLibraries']);

    // Onboarding routes
    Route::get('/onboarding/suggested-libraries', [OnboardingController::class, 'getSuggestedLibraries']);
    Route::post('/onboarding/follow-libraries', [OnboardingController::class, 'followLibraries']);
    Route::get('/onboarding/status', [OnboardingController::class, 'checkOnboardingStatus']);

    // User profile routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'store']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::post('/profile/regenerate-share-key', [ProfileController::class, 'regenerateShareKey']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'getNotifications']);
    Route::get('/notification', [NotificationController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notification/{id}/read', [NotificationController::class, 'markAsRead']);

    // Device registration for push notifications
    Route::post('/device/register', [\App\Http\Controllers\DeviceController::class, 'registerDevice']);
    Route::post('/device/unregister', [\App\Http\Controllers\DeviceController::class, 'unregisterDevice']);

    // Readlist routes
    // User-specific readlist routes (must come BEFORE the parameter routes)
    Route::get('/readlists/user', [ReadlistController::class, 'getUserReadlists']);
    Route::get('/readlist/user', [ReadlistController::class, 'getUserReadlists']);

    // Create readlist routes
    Route::post('/readlists', [ReadlistController::class, 'store']);
    Route::post('/readlist', [ReadlistController::class, 'store']);

    // Routes with ID parameters (must come AFTER more specific routes to avoid conflicts)
    Route::get('/readlists/{id}', [ReadlistController::class, 'show']);
    Route::get('/readlist/{id}', [ReadlistController::class, 'show']);
    Route::put('/readlists/{id}', [ReadlistController::class, 'update']);
    Route::put('/readlist/{id}', [ReadlistController::class, 'update']);
    Route::delete('/readlists/{id}', [ReadlistController::class, 'destroy']);
    Route::delete('/readlist/{id}', [ReadlistController::class, 'destroy']);

    // Readlist item management routes
    Route::post('/readlists/{id}/items', [ReadlistController::class, 'addItem']);
    Route::post('/readlist/{id}/items', [ReadlistController::class, 'addItem']);
    Route::post('/readlist/{id}/item', [ReadlistController::class, 'addItem']);
    Route::post('/readlists/{id}/item', [ReadlistController::class, 'addItem']);
    Route::post('/readlists/{id}/urls', [ReadlistController::class, 'addUrl']);
    Route::post('/readlist/{id}/urls', [ReadlistController::class, 'addUrl']);
    Route::post('/readlist/{id}/url', [ReadlistController::class, 'addUrl']);
    Route::post('/readlists/{id}/url', [ReadlistController::class, 'addUrl']);
    Route::delete('/readlists/{id}/items/{itemId}', [ReadlistController::class, 'removeItem']);
    Route::delete('/readlist/{id}/items/{itemId}', [ReadlistController::class, 'removeItem']);
    Route::delete('/readlist/{id}/item/{itemId}', [ReadlistController::class, 'removeItem']);
    Route::delete('/readlists/{id}/item/{itemId}', [ReadlistController::class, 'removeItem']);
    Route::post('/readlists/{id}/reorder', [ReadlistController::class, 'reorderItems']);
    Route::post('/readlist/{id}/reorder', [ReadlistController::class, 'reorderItems']);
    Route::post('/readlists/{id}/regenerate-key', [ReadlistController::class, 'regenerateShareKey']);
    Route::post('/readlist/{id}/regenerate-key', [ReadlistController::class, 'regenerateShareKey']);
    Route::post('/readlists/{readlist}/links', [ReadlistController::class, 'addExternalLink']);

    // Open Library routes
    Route::get('/user/libraries', [OpenLibraryController::class, 'getUserLibraries']);
    Route::get('/user/entries', [OpenLibraryController::class, 'getUserEntries']);
    Route::get('/libraries/favorites', [OpenLibraryController::class, 'getFavorites']);
    Route::get('/libraries/sections', [OpenLibraryController::class, 'getSections']);
    Route::get('/library/sections', [OpenLibraryController::class, 'getSections']);
    Route::get('/libraries/recently-viewed', [OpenLibraryController::class, 'recentlyViewed']);
    Route::get('/library/recently-viewed', [OpenLibraryController::class, 'recentlyViewed']);
    Route::get('/libraries', [OpenLibraryController::class, 'index']);
    Route::get('/library', [OpenLibraryController::class, 'index']);
    Route::post('/libraries', [OpenLibraryController::class, 'store']);
    Route::post('/library', [OpenLibraryController::class, 'store']);
    Route::get('/libraries/{id}', [OpenLibraryController::class, 'show']);
    Route::get('/library/{id}', [OpenLibraryController::class, 'show']);
    Route::get('/libraries/{id}/private', [OpenLibraryController::class, 'getPrivatePage']);
    Route::get('/library/{id}/private', [OpenLibraryController::class, 'getPrivatePage']);
    Route::put('/libraries/{id}', [OpenLibraryController::class, 'update']);
    Route::put('/library/{id}', [OpenLibraryController::class, 'update']);
    Route::delete('/libraries/{id}', [OpenLibraryController::class, 'destroy']);
    Route::delete('/library/{id}', [OpenLibraryController::class, 'destroy']);
    Route::post('/libraries/{id}/refresh', [OpenLibraryController::class, 'refreshLibrary']);
    Route::post('/library/{id}/refresh', [OpenLibraryController::class, 'refreshLibrary']);
    Route::post('/libraries/{id}/content', [OpenLibraryController::class, 'addContent']);
    Route::post('/library/{id}/content', [OpenLibraryController::class, 'addContent']);
    Route::post('/libraries/{id}/urls', [OpenLibraryController::class, 'addUrl']);
    Route::post('/library/{id}/urls', [OpenLibraryController::class, 'addUrl']); 
    Route::post('/libraries/{id}/url', [OpenLibraryController::class, 'addUrl']);
    Route::post('/library/{id}/url', [OpenLibraryController::class, 'addUrl']);
    
    // Smart URL addition with AI categorization
    Route::post('/libraries/smart-add', [OpenLibraryController::class, 'smartAddUrl']);
    Route::post('/library/smart-add', [OpenLibraryController::class, 'smartAddUrl']);
    
    Route::delete('/libraries/{id}/urls', [OpenLibraryController::class, 'removeUrl']);
    Route::delete('/library/{id}/urls', [OpenLibraryController::class, 'removeUrl']);
    Route::delete('/libraries/{id}/url', [OpenLibraryController::class, 'removeUrl']);
    Route::delete('/library/{id}/url', [OpenLibraryController::class, 'removeUrl']);
    Route::delete('/libraries/{id}/content', [OpenLibraryController::class, 'removeContent']);
    Route::delete('/library/{id}/content', [OpenLibraryController::class, 'removeContent']);
    
    // URL metadata refresh routes
    Route::post('/libraries/{id}/urls/{urlId}/refresh', [OpenLibraryController::class, 'refreshUrlMetadata']);
    Route::post('/libraries/{id}/refresh-all-urls', [OpenLibraryController::class, 'refreshAllUrlMetadata']);

    // Library Follow routes
    Route::post('/libraries/{id}/follow', [LibraryFollowController::class, 'followToggle']);
    Route::post('/library/{id}/follow', [LibraryFollowController::class, 'followToggle']);
    Route::post('/libraries/{id}/follow-toggle', [LibraryFollowController::class, 'followToggle']);
    Route::post('/library/{id}/follow-toggle', [LibraryFollowController::class, 'followToggle']);
    Route::get('/libraries/followed', [LibraryFollowController::class, 'getFollowedLibraries']);
    Route::get('/library/followed', [LibraryFollowController::class, 'getFollowedLibraries']);
    
    // User Follow routes
    Route::post('/users/{userId}/follow', [\App\Http\Controllers\UserFollowController::class, 'follow']);
    Route::delete('/users/{userId}/follow', [\App\Http\Controllers\UserFollowController::class, 'unfollow']);
    Route::post('/users/{userId}/unfollow', [\App\Http\Controllers\UserFollowController::class, 'unfollow']);
    Route::get('/users/{userId}/is-following', [\App\Http\Controllers\UserFollowController::class, 'checkFollowing']);
    Route::get('/users/following', [\App\Http\Controllers\UserFollowController::class, 'following']);
    Route::get('/users/followers', [\App\Http\Controllers\UserFollowController::class, 'followers']);
    
    // User Block routes
    Route::post('/users/{userId}/block', [\App\Http\Controllers\UserBlockController::class, 'blockUser']);
    Route::post('/users/{userId}/unblock', [\App\Http\Controllers\UserBlockController::class, 'unblockUser']);
    Route::post('/users/{userId}/block-toggle', [\App\Http\Controllers\UserBlockController::class, 'toggleBlock']);
    Route::get('/users/{userId}/block-status', [\App\Http\Controllers\UserBlockController::class, 'checkBlockStatus']);
    Route::get('/users/blocked', [\App\Http\Controllers\UserBlockController::class, 'getBlockedUsers']);
    
    // Library listing with follow state
    Route::get('/libraries', [LibraryFollowController::class, 'listLibraries']);
    Route::get('/library', [LibraryFollowController::class, 'listLibraries']);

    // Stub routes to satisfy mobile calls
    Route::get('/hive/channels', function () {
        return response()->json(['channels' => []]);
    });
    Route::get('/courses', function () {
        return response()->json([
            'recommended_courses' => [],
            'courses_by_topic' => [],
        ]);
    });

    // Library URL Like routes
    Route::post('/library-urls/{urlId}/like', [LikeController::class, 'likeLibraryUrl']);
    Route::get('/library-urls/{urlId}/like-count', [LikeController::class, 'libraryUrlLikeCount']);
    
    // Library URL Vote routes (upvote/downvote) - Reddit Style
    Route::post('/library-urls/{urlId}/vote', [LikeController::class, 'voteLibraryUrl']);
    Route::get('/library-urls/{urlId}/votes', [LikeController::class, 'getLibraryUrlVotes']);

    // Library URL Report routes
    Route::post('/library-urls/{urlId}/report', [ReportController::class, 'reportLibraryUrl']);

    // Comment routes (posts + library URLs)
    Route::get('/comments/{id}', [\App\Http\Controllers\CommentController::class, 'index'])
        ->defaults('type', 'post');
    Route::post('/comments/{id}', [\App\Http\Controllers\CommentController::class, 'store'])
        ->defaults('type', 'post');

    Route::get('/comments/{type}/{id}', [\App\Http\Controllers\CommentController::class, 'index'])
        ->where('type', 'library[-_]url');
        
    Route::post('/comments/{type}/{id}', [\App\Http\Controllers\CommentController::class, 'store'])
        ->where('type', 'library[-_]url');

    Route::delete('/comments/{id}', [\App\Http\Controllers\CommentController::class, 'destroy']);
    
    // Comment Report routes
    Route::post('/comments/{id}/report', [ReportController::class, 'reportComment']);

    // Add library URL to readlist
    Route::post('/readlists/{readlistId}/library-url', [ReadlistController::class, 'addLibraryUrlToReadlist']);
    Route::post('/readlist/{readlistId}/library-url', [ReadlistController::class, 'addLibraryUrlToReadlist']);
    
    // Add raw URL to readlist
    Route::post('/readlists/{id}/url', [ReadlistController::class, 'addUrl']);
    Route::post('/readlist/{id}/url', [ReadlistController::class, 'addUrl']);
    
    // AlexPoints routes
    Route::prefix('alex-points')->group(function () {
        Route::get('/summary', [\App\Http\Controllers\AlexPointsController::class, 'summary']);
        Route::get('/transactions', [\App\Http\Controllers\AlexPointsController::class, 'transactions']);
        Route::get('/rules', [\App\Http\Controllers\AlexPointsController::class, 'rules']);
        Route::get('/levels', [\App\Http\Controllers\AlexPointsController::class, 'levels']);
        Route::get('/leaderboard', [\App\Http\Controllers\AlexPointsController::class, 'leaderboard']);
        Route::get('/balance', [\App\Http\Controllers\AlexPointsPaymentController::class, 'getPointsBalance']);
        Route::get('/transaction-history', [\App\Http\Controllers\AlexPointsPaymentController::class, 'getTransactionHistory']);
        
        // Admin-only routes (now accessible to all authenticated users)
        Route::group([], function() {
            Route::post('/rules', [\App\Http\Controllers\AlexPointsController::class, 'createRule']);
            Route::put('/rules/{id}', [\App\Http\Controllers\AlexPointsController::class, 'updateRule']);
            Route::post('/levels', [\App\Http\Controllers\AlexPointsController::class, 'createLevel']);
            Route::put('/levels/{id}', [\App\Http\Controllers\AlexPointsController::class, 'updateLevel']);
            Route::post('/adjust', [\App\Http\Controllers\AlexPointsController::class, 'adjustPoints']);
        });
    });
    
    // Additional points route for compatibility
    Route::get('/users/points', [\App\Http\Controllers\AlexPointsPaymentController::class, 'getPointsBalance']);
    
    // Highlights API routes (v1)
    Route::prefix('v1')->group(function () {
        // Note: More specific routes must come before parameterized routes
        Route::get('/highlights/stats', [\App\Http\Controllers\HighlightController::class, 'getHighlightStats']);
        Route::get('/highlights', [\App\Http\Controllers\HighlightController::class, 'getAllUserHighlights']);
        Route::get('/highlights/{urlId}', [\App\Http\Controllers\HighlightController::class, 'fetchHighlights']);
        Route::post('/highlights', [\App\Http\Controllers\HighlightController::class, 'createHighlight']);
        Route::put('/highlights/{highlightId}', [\App\Http\Controllers\HighlightController::class, 'updateHighlightNote']);
        Route::delete('/highlights/{highlightId}', [\App\Http\Controllers\HighlightController::class, 'deleteHighlight']);
    });
    
    // Admin routes for library management (now accessible to all authenticated users)
    Route::prefix('admin')->group([], function() {
        // Library management
        Route::get('/libraries', [App\Http\Controllers\AdminApiLibraryController::class, 'getLibraries']);
        Route::get('/libraries/{id}', [App\Http\Controllers\AdminApiLibraryController::class, 'getLibrary']);
        Route::post('/libraries/{id}/approve', [App\Http\Controllers\AdminApiLibraryController::class, 'approveLibrary']);
        Route::post('/libraries/{id}/reject', [App\Http\Controllers\AdminApiLibraryController::class, 'rejectLibrary']);
        Route::post('/libraries/{id}/generate-cover', [App\Http\Controllers\AdminApiLibraryController::class, 'generateCoverImage']);

        // Dashboard stats
        Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index']);
        
        // Admin Lists
        Route::get('/users', [\App\Http\Controllers\DashboardController::class, 'getUsers']);
        Route::get('/readlists', [\App\Http\Controllers\DashboardController::class, 'getReadlists']);
    });
});
