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
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReadlistController;
use App\Http\Controllers\EducatorsController;
use App\Http\Controllers\LiveClassController;
use App\Http\Controllers\HireRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\CourseSectionController;
use App\Http\Controllers\CourseLessonController;
use App\Http\Controllers\BookmarkController;

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

// Public educators route - get all educators
Route::get('/educators', [EducatorsController::class, 'getAllEducators']);

// Public Live Class routes (viewing list and details do not require a subscription)
Route::prefix('live-class')->group(function () {
    // List all live classes
    Route::get('/', [LiveClassController::class, 'index']);
    // View details for a specific live class
    Route::get('/{id}', [LiveClassController::class, 'show']);
});

// Public route for accessing shared readlists via share key
Route::get('/readlists/shared/{shareKey}', [ReadlistController::class, 'showByShareKey']);

// Protected routes (authentication required)
// Use auth:sanctum middleware consistently for all protected routes
Route::middleware('auth:sanctum')->group(function() {
    // Protected Live Class routes (actions that modify the class)
    Route::prefix('live-class')->group(function () {
        Route::post('/', [LiveClassController::class, 'store']);
        Route::post('/{id}/join', [LiveClassController::class, 'join']);
        Route::post('/{id}/end', [LiveClassController::class, 'end']);
        // WebRTC and Streaming routes
        Route::post('/{id}/signal', [LiveClassController::class, 'signal']);
        Route::get('/{id}/participants', [LiveClassController::class, 'getParticipants']);
        Route::post('/{id}/start-stream', [LiveClassController::class, 'startStream']);
        Route::post('/{id}/stop-stream', [LiveClassController::class, 'stopStream']);
        Route::get('/{id}/stream-info', [LiveClassController::class, 'getStreamInfo']);
        // Additional WebRTC routes
        Route::post('/{id}/ice-candidate', [LiveClassController::class, 'sendIceCandidate']);
        Route::get('/{id}/room-status', [LiveClassController::class, 'getRoomStatus']);
        Route::post('/{id}/participant-settings', [LiveClassController::class, 'updateParticipantSettings']);
        Route::post('/{id}/connection-quality', [LiveClassController::class, 'reportConnectionQuality']);
    });

    // Educators with follow status - authenticated version
    Route::get('/educators/with-follows', [EducatorsController::class, 'getAllEducatorsWithFollowStatus']);

    Route::post('/logout', [AuthManager::class, 'logout'])->name('logout');
    Route::get('/feed', [FeedController::class, 'feed'])->name('feed');
    Route::get('/user/{id}', [AuthManager::class, 'fetchUser'])->name('user');

    // Profile routes
    Route::get('/profile/{user:username}', [ProfileController::class, 'viewProfile'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'UploadAvatar'])->name('profile.uploadAvatar');

    // User preferences routes
    Route::post('/createPref', [SetupController::class, 'createPref'])->name('createPref');
    Route::post('/savePref', [SetupController::class, 'savePreferences'])->name('savePref');

    // Follow routes
    Route::post('/follow/{id}', [FollowController::class, 'createFollow'])->name('createFollow');
    Route::post('/unfollow/{id}', [FollowController::class, 'unFollow'])->name('unfollow');

    // Courses routes (old - keeping for backward compatibility)
    Route::post('/create-course', [EducatorsController::class, 'createCourse'])->name('postCourse');
    Route::get('/course/{id}', [EducatorsController::class, 'view'])->name('view');

    // Enhanced course management
    Route::post('/courses', [CoursesController::class, 'createCourse']);
    Route::get('/courses', [CoursesController::class, 'listCourses']);
    Route::get('/courses/{id}', [CoursesController::class, 'viewCourse']);
    Route::put('/courses/{id}', [CoursesController::class, 'updateCourse']);
    Route::delete('/courses/{id}', [CoursesController::class, 'deleteCourse']);
    Route::get('/courses/{id}/content', [CoursesController::class, 'getCourseContent']);
    
    // Course sections
    Route::get('/courses/{courseId}/sections', [CourseSectionController::class, 'index']);
    Route::post('/courses/{courseId}/sections', [CourseSectionController::class, 'store']);
    Route::get('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'show']);
    Route::put('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'update']);
    Route::delete('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'destroy']);
    
    // Course lessons
    Route::post('/sections/{sectionId}/lessons', [CourseLessonController::class, 'store']);
    Route::get('/lessons/{lessonId}', [CourseLessonController::class, 'show']);
    Route::put('/lessons/{lessonId}', [CourseLessonController::class, 'update']);
    Route::delete('/lessons/{lessonId}', [CourseLessonController::class, 'destroy']);
    Route::post('/lessons/{lessonId}/complete', [CourseLessonController::class, 'markComplete']);
    Route::get('/courses-by-topic', [CoursesController::class, 'getCoursesByTopic']);

    // Post routes
    Route::post('/post', [PostController::class, 'storePost'])->name('post');
    Route::get('/viewPost', [PostController::class, 'viewSinglePost'])->name('viewPost');

    // Comment routes
    Route::post('/post/{post}/comment', [CommentController::class, 'postComment'])->name('post.comment');
    Route::get('/posts/{post}/comments', [CommentController::class, 'displayComments']);

    // Like routes
    Route::post('/post/{post}/like', [LikeController::class, 'createLike'])->name('like.post');
    Route::get('/post_likes/{postId}', [LikeController::class, 'post_like_count']);
    Route::get('/comment_likes/{commentId}', [LikeController::class, 'comment_like_count']);
    Route::get('/course_likes/{courseId}', [LikeController::class, 'course_like_count']);

    // Like a comment
    Route::post('/comment/{comment}/like', [LikeController::class, 'createLike'])->name('like.comment');
    
    // Like a course
    Route::post('/course/{course}/like', [LikeController::class, 'createLike'])->name('like.course');

    // Bookmark routes
    Route::post('/course/{courseId}/bookmark', [BookmarkController::class, 'bookmarkCourse']);
    Route::post('/post/{lessonId}/bookmark', [BookmarkController::class, 'bookmarkPost']);
    Route::delete('/course/{courseId}/bookmark', [BookmarkController::class, 'removeBookmarkCourse']);
    Route::delete('/post/{lessonId}/bookmark', [BookmarkController::class, 'removeBookmarkPost']);

    // Readlist routes
    Route::prefix('readlists')->group(function () {
        Route::get('/', [ReadlistController::class, 'index']);
        Route::post('/', [ReadlistController::class, 'store']);
        Route::get('/public', [ReadlistController::class, 'publicReadlists']);
        Route::get('/{id}', [ReadlistController::class, 'show']);
        Route::put('/{id}', [ReadlistController::class, 'update']);
        Route::delete('/{id}', [ReadlistController::class, 'destroy']);
        
        // Readlist item routes
        Route::post('/{id}/items', [ReadlistController::class, 'addItem']);
        Route::delete('/{id}/items/{itemId}', [ReadlistController::class, 'removeItem']);
        Route::put('/{id}/reorder', [ReadlistController::class, 'reorderItems']);
        Route::post('/{id}/regenerate-share-key', [ReadlistController::class, 'regenerateShareKey']);
    });

    // Search route
    Route::get('/search', [SearchController::class, 'search'])->name('search');

    // Hire request routes
    Route::post('/hire-request', [HireRequestController::class, 'sendRequest']);
    Route::patch('/hire-request/{id}/accept', [HireRequestController::class, 'acceptRequest']);
    Route::patch('/hire-request/{id}/decline', [HireRequestController::class, 'declineRequest']);
    Route::get('/hire-requests', [HireRequestController::class, 'listRequests']);
    Route::delete('/hire-requests/{id}', [HireRequestController::class, 'cancelRequest']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // Setup status
    Route::get('/setup_status', [SetupController::class, 'checkSetupStatus']);

    // Chat route - using auth:sanctum consistently
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
    });
    
    // Messaging routes
    Route::prefix('messages')->group(function () {
        // Get all conversations for authenticated user
        Route::get('/conversations', [MessageController::class, 'getConversations']);
        
        // Get a specific conversation with messages
        Route::get('/conversations/{id}', [MessageController::class, 'getConversation']);
        
        // Send a message
        Route::post('/send', [MessageController::class, 'sendMessage']);
        
        // Mark all messages in a conversation as read
        Route::post('/conversations/{id}/read', [MessageController::class, 'markAsRead']);
        
        // Delete a message (soft delete)
        Route::delete('/messages/{id}', [MessageController::class, 'deleteMessage']);
        
        // Get count of unread messages
        Route::get('/unread-count', [MessageController::class, 'getUnreadCount']);
    });

    // Subscription routes
    Route::prefix('subscription')->group(function () {
        Route::post('/initiate', [PaystackController::class, 'initiateSubscription']);
        Route::get('/verify/{reference}', [PaystackController::class, 'verifyPayment']);
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::get('/upcoming', [SubscriptionController::class, 'upcoming']);
        Route::get('/history', [SubscriptionController::class, 'history']);
    });
    
    Route::get('/live-class/subscription-status', [LiveClassController::class, 'checkSubscriptionStatus']);

    // Enrollment management
    Route::post('/enroll/{courseId}', [EnrollmentController::class, 'enrollInCourse']);
    Route::get('/enrollments', [EnrollmentController::class, 'getUserEnrollments']);
    Route::post('/enrollments/{id}/progress', [EnrollmentController::class, 'updateProgress']);
});

// Public routes for payment and webhooks
Route::post('/paystack/webhook', [PaystackController::class, 'handleWebhook']);
Route::post('/subscription/free', [PaystackController::class, 'createFreeSubscription']);
Route::post('/enrollment/verify', [EnrollmentController::class, 'verifyEnrollment'])->name('enrollment.verify');
Route::post('/enrollment/webhook', [EnrollmentController::class, 'handleWebhook']);