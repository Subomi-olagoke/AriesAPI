<?php

use App\Models\User;
use App\Events\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthManager;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EducatorsController;
use App\Http\Controllers\LiveClassController;
use App\Http\Controllers\HireRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\SubscriptionController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthManager::class, 'register'])->name('register');
Route::post('/login', [AuthManager::class, 'login'])->name('login');
Route::post('/forgotPassword', [AuthManager::class, 'forgorPassword'])->name('resetPassReq');
Route::post('/resetPassword', [AuthManager::class, 'resetPassword'])->name('resetPassword');
Route::post('/setup', [SetupController::class, 'setup'])->name('setup');
Route::post('/createPreferences', [SetupController::class, 'createPreferences'])->name('createPreferences');
Route::get('/followOptions', [SetupController::class, 'followOptions'])->name('followOptions');

// Protected routes
Route::middleware(['auth:sanctum'])->group(function() {
    // Live Class routes
    Route::prefix('live-class')->group(function () {
        Route::post('/', [LiveClassController::class, 'store']);
        Route::get('/{id}', [LiveClassController::class, 'show']);
        Route::post('/{id}/join', [LiveClassController::class, 'join']);
        Route::post('/{id}/end', [LiveClassController::class, 'end']);
        // WebRTC and Streaming routes
        Route::post('/{id}/signal', [LiveClassController::class, 'signal']);
        Route::get('/{id}/participants', [LiveClassController::class, 'getParticipants']);
        Route::post('/{id}/start-stream', [LiveClassController::class, 'startStream']);
        Route::post('/{id}/stop-stream', [LiveClassController::class, 'stopStream']);
        Route::get('/{id}/stream-info', [LiveClassController::class, 'getStreamInfo']);
    });

    Route::post('/logout', [AuthManager::class, 'logout'])->name('logout');
    Route::get('/feed', [FeedController::class, 'feed'])->name('feed');
    Route::get('/user/{id}', [AuthManager::class, 'fetchUser'])->name('user');

    // Profile routes
    Route::get('/profile/{user:username}', [ProfileController::class, 'viewProfile'])->name('profile');

    // User preferences routes
    Route::post('/createPref', [SetupController::class, 'createPref'])->name('createPref');
    Route::post('/savePref', [SetupController::class, 'savePreferences'])->name('savePref');

    // Follow routes
    Route::post('/follow/{id}', [FollowController::class, 'createFollow'])->name('createFollow');
    Route::post('/unfollow/{id}', [FollowController::class, 'unFollow'])->name('unfollow');

    // Courses route
    Route::post('/create-course', [EducatorsController::class, 'createCourse'])->name('postCourse');
    Route::get('/course/{id}', [EducatorsController::class, 'view'])->name('view');

    // Post routes
    Route::post('/post', [PostController::class, 'storePost'])->name('post');
    Route::get('/viewPost', [PostController::class, 'viewSinglePost'])->name('viewPost');

    // Comment route
    Route::post('/post/{post}/comment', [CommentController::class, 'postComment'])->name('post.comment');
    Route::get('/posts/{post}/comments', [CommentController::class, 'displayComments']);

    // Like routes
    Route::post('/post/{post}/like', [LikeController::class, 'createLike'])->name('like.post');

    Route::get('/post_likes/{postId}', [LikeController::class, 'post_like_count']);
    Route::get('/comment_likes/{commentId}', [LikeController::class, 'comment_like_count']);
    Route::get('/course_likes/{courseId', [LikeController::class, 'course_like_count']);

    // Like a comment
    Route::post('/comment/{comment}/like', [LikeController::class, 'createLike'])->middleware('auth:api')->name('like.comment');

    // Like a course
    Route::post('/course/{course}/like', [LikeController::class, 'createLike'])->middleware('auth:api')->name('like.course');
    Route::post('/comment/{comment}/like', [LikeController::class, 'createLike'])
        ->middleware('auth:api')
        ->name('like.comment');
    Route::post('/course/{course}/like', [LikeController::class, 'createLike'])
        ->middleware('auth:api')
        ->name('like.course');

    Route::get('/search', [SearchController::class, 'search'])->name('search');

    // Hire request routes
    Route::post('/hire-request', [HireRequestController::class, 'sendRequest']);
    Route::patch('/hire-request/{id}/accept', [HireRequestController::class, 'acceptRequest']);
    Route::patch('/hire-request/{id}/decline', [HireRequestController::class, 'declineRequest']);
    Route::get('/hire-requests', [HireRequestController::class, 'listRequests']);
    Route::delete('/hire-requests/{id}', [HireRequestController::class, 'cancelRequest']);

    Route::get('/notifications', [NotificationController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // Setup status
    Route::get('/setup_status', [SetupController::class, 'checkSetupStatus']);

    // Chat route
    Route::post('/send-chat-message', function(Request $request) {
        $formFields = $request->validate([
            'textvalue' => 'required'
        ]);
        if (!trim(strip_tags($formFields['textvalue']))) {
            return response()->noContent();
        }
        broadcast(new ChatMessage([
            'username'  => auth()->user()->username,
            'textvalue' => strip_tags($request->textvalue),
            'avatar'    => auth()->user()->avatar
        ]))->toOthers();
        return response()->noContent();
    })->middleware('mustBeLoggedIn');
});

// Live Class additional routes
Route::post('/{id}/ice-candidate', [LiveClassController::class, 'sendIceCandidate']);
Route::get('/{id}/room-status', [LiveClassController::class, 'getRoomStatus']);
Route::post('/{id}/participant-settings', [LiveClassController::class, 'updateParticipantSettings']);
Route::post('/{id}/connection-quality', [LiveClassController::class, 'reportConnectionQuality']);

// Subscription routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/subscription/initiate', [PaystackController::class, 'initiateSubscription']);
    Route::get('/subscription/verify/{reference}', [PaystackController::class, 'verifyPayment']);
});

// Paystack webhook
Route::post('/paystack/webhook', [PaystackController::class, 'handleWebhook']);

// Subscription status routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('subscription')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::get('/upcoming', [SubscriptionController::class, 'upcoming']);
        Route::get('/history', [SubscriptionController::class, 'history']);
    });
    Route::get('/live-class/subscription-status', [LiveClassController::class, 'checkSubscriptionStatus']);
});