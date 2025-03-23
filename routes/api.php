<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthManager;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CogniController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\CourseLesson;
use App\Http\Controllers\CourseLessonController;
use App\Http\Controllers\CourseSectionController;
use App\Http\Controllers\EnhancedCogniController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EducatorsController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ForgotPasswordManager;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\HireController;
use App\Http\Controllers\HireRequestController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LiveClassController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpenLibraryController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReadlistController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SubscriptionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthManager::class, 'register']);
Route::post('/login', [AuthManager::class, 'login']);
Route::post('/forgot-password', [ForgotPasswordManager::class, 'forgotPassword']);
Route::post('/reset-password', [ForgotPasswordManager::class, 'resetPassword']);
Route::post('/reset-password/token', [ResetPasswordController::class, 'generateResetToken']);
Route::post('/reset-password/reset', [ResetPasswordController::class, 'resetPassword']);

// Protected routes that require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthManager::class, 'logout']);

    // User profile routes
    Route::get('/profile/{user}', [ProfileController::class, 'viewProfile']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'UploadAvatar']);

    // Post routes
    Route::get('/post/{post}', [PostController::class, 'viewSinglePost']);
    Route::post('/create-post', [PostController::class, 'storePost']);
    Route::delete('/posts/{post}', [PostController::class, 'deletePost']);
    Route::get('/posts/user/{userId?}', [PostController::class, 'getUserPosts']);
    Route::get('/posts/{postId}/stats', [PostController::class, 'getPostStats']);

    // Comment routes
    Route::post('/add-comment/{post_id}', [CommentController::class, 'postComment']);
    Route::delete('/comments/{commentId}', [CommentController::class, 'deleteComment']);
    Route::get('/comments/{post}', [CommentController::class, 'displayComments']);
    Route::get('/comments/{postId}/count', [CommentController::class, 'getCommentCount']);

    // Feed route
    Route::get('/feed', [FeedController::class, 'feed']);

    // Like routes
    Route::post('/like/{post}', [LikeController::class, 'createLike']);
    Route::post('/like/comment/{comment}', [LikeController::class, 'createLike']);
    Route::post('/like/course/{course}', [LikeController::class, 'createLike']);
    Route::get('/like/{postId}/count', [LikeController::class, 'post_like_count']);
    Route::get('/like/comment/{commentId}/count', [LikeController::class, 'comment_like_count']);
    Route::get('/like/course/{courseId}/count', [LikeController::class, 'course_like_count']);

    // Follow routes
    Route::post('/follow/{username}', [FollowController::class, 'createFollow']);
    Route::delete('/unfollow/{username}', [FollowController::class, 'unFollow']);
    Route::get('/follow/{username}/status', [FollowController::class, 'checkFollowStatus']);
    Route::get('/followers/{username}', [FollowController::class, 'getFollowers']);
    Route::get('/following/{username}', [FollowController::class, 'getFollowing']);

    // Hire routes
    Route::post('/hire/{id}', [HireController::class, 'hireInstructor']);
    Route::delete('/hire/{id?}', [HireController::class, 'endHireSession']);

    // Hire Request routes
    Route::post('/hire-requests', [HireRequestController::class, 'sendRequest']);
    Route::post('/hire-requests/{id}/accept', [HireRequestController::class, 'acceptRequest']);
    Route::post('/hire-requests/{id}/decline', [HireRequestController::class, 'declineRequest']);
    Route::delete('/hire-requests/{id}', [HireRequestController::class, 'cancelRequest']);
    Route::get('/hire-requests', [HireRequestController::class, 'listRequests']);

    // Setup routes
    Route::post('/setup', [SetupController::class, 'setup']);
    Route::get('/preferences', [SetupController::class, 'createPreferences']);
    Route::post('/preferences', [SetupController::class, 'savePreferences']);
    Route::get('/follow-options', [SetupController::class, 'followOptions']);
    Route::get('/setup-status', [SetupController::class, 'checkSetupStatus']);

    // Course routes
    Route::post('/courses', [CoursesController::class, 'createCourse']);
    Route::get('/courses/{id}', [CoursesController::class, 'viewCourse']);
    Route::get('/courses/{id}/content', [CoursesController::class, 'getCourseContent']);
    Route::get('/courses', [CoursesController::class, 'listCourses']);
    Route::put('/courses/{id}', [CoursesController::class, 'updateCourse']);
    Route::delete('/courses/{id}', [CoursesController::class, 'deleteCourse']);
    Route::get('/courses-by-topic', [CoursesController::class, 'getCoursesByTopic']);

    // Course Section routes
    Route::get('/courses/{courseId}/sections', [CourseSectionController::class, 'index']);
    Route::post('/courses/{courseId}/sections', [CourseSectionController::class, 'store']);
    Route::get('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'show']);
    Route::put('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'update']);
    Route::delete('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'destroy']);

    // Course Lesson routes
    Route::post('/sections/{sectionId}/lessons', [CourseLessonController::class, 'store']);
    Route::get('/lessons/{lessonId}', [CourseLessonController::class, 'show']);
    Route::put('/lessons/{lessonId}', [CourseLessonController::class, 'update']);
    Route::delete('/lessons/{lessonId}', [CourseLessonController::class, 'destroy']);
    Route::post('/lessons/{lessonId}/complete', [CourseLessonController::class, 'markComplete']);

    // Live Class routes
    Route::prefix('live-classes')->group(function () {
        Route::get('/', [LiveClassController::class, 'index']);
        Route::get('/{liveClass}', [LiveClassController::class, 'show']);
        Route::post('/', [LiveClassController::class, 'store']);
        Route::post('/{liveClass}/join', [LiveClassController::class, 'join']);
        Route::post('/{liveClass}/end', [LiveClassController::class, 'end']);
        Route::post('/{classId}/signal', [LiveClassController::class, 'signal']);
        Route::post('/{classId}/ice-candidate', [LiveClassController::class, 'sendIceCandidate']);
        Route::get('/{liveClass}/status', [LiveClassController::class, 'getRoomStatus']);
        Route::put('/{liveClass}/participant-settings', [LiveClassController::class, 'updateParticipantSettings']);
        Route::get('/{liveClass}/participants', [LiveClassController::class, 'getParticipants']);
        Route::post('/{liveClass}/start-stream', [LiveClassController::class, 'startStream']);
        Route::post('/{liveClass}/stop-stream', [LiveClassController::class, 'stopStream']);
        Route::get('/{liveClass}/stream-info', [LiveClassController::class, 'getStreamInfo']);
        Route::post('/{liveClass}/report-connection', [LiveClassController::class, 'reportConnectionQuality']);
    });
    
    Route::get('/subscription/status', [LiveClassController::class, 'checkSubscriptionStatus']);

    // Educator routes
    Route::post('/create/course', [EducatorsController::class, 'createCourse']);
    Route::get('/dashboard/courses', [EducatorsController::class, 'showEducatorCourses']);
    Route::get('/course/{id}', [EducatorsController::class, 'view']);
    Route::get('/download/{file}', [EducatorsController::class, 'download']);
    Route::get('/educators', [EducatorsController::class, 'getAllEducators']);
    Route::get('/educators/with-follow-status', [EducatorsController::class, 'getAllEducatorsWithFollowStatus']);

    // Enrollment routes
    Route::post('/courses/{courseId}/enroll', [EnrollmentController::class, 'enrollInCourse']);
    Route::post('/enrollment/verify', [EnrollmentController::class, 'verifyEnrollment'])->name('enrollment.verify');
    Route::get('/enrollments', [EnrollmentController::class, 'getUserEnrollments']);
    Route::post('/enrollments/{enrollmentId}/progress', [EnrollmentController::class, 'updateProgress']);
    Route::post('/courses/{courseId}/enroll/saved-card', [EnrollmentController::class, 'enrollWithSavedCard']);

    // Bookmark routes
    Route::post('/bookmark/course/{courseId}', [BookmarkController::class, 'bookmarkCourse']);
    Route::post('/bookmark/post/{lessonId}', [BookmarkController::class, 'bookmarkPost']);
    Route::delete('/bookmark/course/{courseId}', [BookmarkController::class, 'removeBookmarkCourse']);
    Route::delete('/bookmark/post/{lessonId}', [BookmarkController::class, 'removeBookmarkPost']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // Messaging routes
    Route::get('/conversations', [MessageController::class, 'getConversations']);
    Route::get('/conversations/{id}', [MessageController::class, 'getConversation']);
    Route::post('/messages', [MessageController::class, 'sendMessage']);
    Route::post('/conversations/{conversationId}/read', [MessageController::class, 'markAsRead']);
    Route::delete('/messages/{messageId}', [MessageController::class, 'deleteMessage']);
    Route::get('/messages/unread-count', [MessageController::class, 'getUnreadCount']);

    // Readlist routes
    Route::get('/readlists', [ReadlistController::class, 'getUserReadlists']);
    Route::post('/readlists', [ReadlistController::class, 'store']);
    Route::get('/readlists/{id}', [ReadlistController::class, 'show']);
    Route::put('/readlists/{id}', [ReadlistController::class, 'update']);
    Route::delete('/readlists/{id}', [ReadlistController::class, 'destroy']);
    Route::post('/readlists/{id}/items', [ReadlistController::class, 'addItem']);
    Route::delete('/readlists/{id}/items/{itemId}', [ReadlistController::class, 'removeItem']);
    Route::post('/readlists/{id}/reorder', [ReadlistController::class, 'reorderItems']);
    Route::post('/readlists/{id}/regenerate-key', [ReadlistController::class, 'regenerateShareKey']);

    // Open Library routes
    Route::get('/libraries', [OpenLibraryController::class, 'index']);
    Route::post('/libraries', [OpenLibraryController::class, 'store']);
    Route::get('/libraries/{id}', [OpenLibraryController::class, 'show']);
    Route::put('/libraries/{id}', [OpenLibraryController::class, 'update']);
    Route::delete('/libraries/{id}', [OpenLibraryController::class, 'destroy']);
    Route::post('/libraries/{id}/refresh', [OpenLibraryController::class, 'refreshLibrary']);
    Route::post('/libraries/{id}/content', [OpenLibraryController::class, 'addContent']);
    Route::delete('/libraries/{id}/content', [OpenLibraryController::class, 'removeContent']);
    Route::post('/libraries/dynamic', [OpenLibraryController::class, 'createDynamicLibrary']);
    Route::get('/courses/{courseId}/libraries', [OpenLibraryController::class, 'getCourseLibraries']);
    Route::get('/libraries/similar', [OpenLibraryController::class, 'getSimilarLibraries']);

    // Cogni AI Assistant routes
    Route::post('/cogni/ask', [CogniController::class, 'ask']);
    Route::get('/cogni/conversations', [CogniController::class, 'getConversations']);
    Route::get('/cogni/conversations/{conversationId}', [CogniController::class, 'getConversationHistory']);
    Route::post('/cogni/explain', [CogniController::class, 'explain']);
    Route::post('/cogni/generate-quiz', [CogniController::class, 'generateQuiz']);
    Route::post('/cogni/conversations/clear', [CogniController::class, 'clearConversation']);

    // Enhanced Cogni routes
    Route::post('/cogni/enhanced/readlist', [EnhancedCogniController::class, 'generateReadlist']);
    Route::get('/cogni/enhanced/readlist/{id}/analyze', [EnhancedCogniController::class, 'analyzeReadlist']);
    Route::get('/cogni/enhanced/readlist/{id}/recommend', [EnhancedCogniController::class, 'recommendForReadlist']);
    Route::get('/cogni/enhanced/readlist/{id}/assessments', [EnhancedCogniController::class, 'generateAssessments']);
    Route::post('/cogni/enhanced/readlist/{id}/study-plan', [EnhancedCogniController::class, 'createStudyPlan']);
    Route::get('/cogni/enhanced/recommend-educators', [EnhancedCogniController::class, 'recommendEducators']);
    Route::post('/cogni/enhanced/ask', [EnhancedCogniController::class, 'ask']);

    // Search route
    Route::get('/search', [SearchController::class, 'search']);

    // Subscription routes
    Route::post('/subscriptions/initiate', [PaystackController::class, 'initiateSubscription']);
    Route::get('/subscriptions/verify/{reference}', [PaystackController::class, 'verifyPayment']);
    Route::post('/subscriptions/cancel', [PaystackController::class, 'cancelSubscription']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
    Route::get('/subscriptions/upcoming', [SubscriptionController::class, 'upcoming']);
    Route::get('/subscriptions/history', [SubscriptionController::class, 'history']);
    Route::post('/subscriptions/free', [PaystackController::class, 'createFreeSubscription']);
    Route::post('/payment/retry', [PaystackController::class, 'retryPayment']);
    
    // Payment methods routes
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index']);
        Route::post('/initiate', [PaymentMethodController::class, 'initiate']);
        Route::post('/{id}/default', [PaymentMethodController::class, 'setDefault']);
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy']);
    });
});

// Webhook routes (no authentication required)
Route::post('/webhooks/paystack', [PaystackController::class, 'handleWebhook']);
Route::post('/webhooks/enrollment', [EnrollmentController::class, 'handleWebhook']);

// Public readlist share route
Route::get('/readlists/shared/{shareKey}', [ReadlistController::class, 'showByShareKey']);

// Public payment verification route
Route::get('/payment-methods/verify', [PaymentMethodController::class, 'verify'])->name('payment-methods.verify');

// Success and failure routes for payment methods and subscriptions
Route::get('/payment-methods/success', function() {
    return view('payment-methods.success');
})->name('payment-methods.success');

Route::get('/payment-methods/failed', function() {
    return view('payment-methods.failed');
})->name('payment-methods.failed');

Route::get('/subscription/success', function() {
    return view('subscription.success');
})->name('subscription.success');

Route::get('/subscription/failed', function() {
    return view('subscription.failed');
})->name('subscription.failed');