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
use App\Http\Controllers\EducatorProfileController;
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
use App\Http\Controllers\FileController;

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
// Public route to view a shared post
//
Route::get('/posts/shared/{shareKey}', [PostController::class, 'viewSharedPost'])
     ->name('shared.post');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthManager::class, 'register']);
Route::post('/login', [AuthManager::class, 'login']);
Route::post('/forgot-password', [ForgotPasswordManager::class, 'forgotPassword']);
Route::post('/reset-password', [ForgotPasswordManager::class, 'resetPassword']);
Route::post('/reset-password/token', [ResetPasswordController::class, 'generateResetToken']);
Route::post('/reset-password/reset', [ResetPasswordController::class, 'resetPassword']);
Route::post('/auth/google', [\App\Http\Controllers\GoogleController::class, 'authenticateWithGoogle']);

// Protected routes that require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthManager::class, 'logout']);

    // User profile routes
    Route::get('/profile/{user}', [ProfileController::class, 'viewProfile']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'UploadAvatar']);
    Route::get('/educators/{username}/profile', [EducatorProfileController::class, 'show']);
    Route::post('/profile/educator', [ProfileController::class, 'updateEducatorProfile']);

    // Post routes - include both singular and plural paths
    Route::get('/post/{post}', [PostController::class, 'viewSinglePost']);
    Route::get('/posts/{post}', [PostController::class, 'viewSinglePost']); // Additional plural route
    Route::post('/post', [PostController::class, 'store']); // Add singular route
    Route::post('/create-post', [PostController::class, 'store']);
    Route::post('/posts', [PostController::class, 'store']); // Additional plural route
    Route::delete('/post/{post}', [PostController::class, 'deletePost']); // Additional singular route
    Route::delete('/posts/{post}', [PostController::class, 'deletePost']);
    Route::get('/post/user/{userId?}', [PostController::class, 'getUserPosts']); // Additional singular route
    Route::get('/posts/user/{userId?}', [PostController::class, 'getUserPosts']);
    Route::get('/post/{postId}/stats', [PostController::class, 'getPostStats']); // Additional singular route
    Route::get('/posts/{postId}/stats', [PostController::class, 'getPostStats']);
    Route::get('/post/{postId}/selections', [PostController::class, 'getSelectionCount']); // Additional singular route
    Route::get('/posts/{postId}/selections', [PostController::class, 'getSelectionCount']);

    // Comment routes
    Route::post('/add-comment/{post_id}', [CommentController::class, 'postComment']);
    Route::post('/comment/{post_id}', [CommentController::class, 'postComment']); // Additional route
    Route::delete('/comment/{commentId}', [CommentController::class, 'deleteComment']); // Additional route
    Route::delete('/comments/{commentId}', [CommentController::class, 'deleteComment']);
    Route::get('/comment/{post}', [CommentController::class, 'displayComments']); // Additional route
    Route::get('/comments/{post}', [CommentController::class, 'displayComments']);
    Route::get('/comment/{postId}/count', [CommentController::class, 'getCommentCount']); // Additional route
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

    // Hire route

    // Hire Request routes
    Route::post('/hire-requests', [HireRequestController::class, 'sendRequest']);
    Route::post('/hire-request', [HireRequestController::class, 'sendRequest']); // Additional route
    Route::post('/hire-requests/{id}/accept', [HireRequestController::class, 'acceptRequest']);
    Route::post('/hire-request/{id}/accept', [HireRequestController::class, 'acceptRequest']); // Additional route
    Route::post('/hire-requests/{id}/decline', [HireRequestController::class, 'declineRequest']);
    Route::post('/hire-request/{id}/decline', [HireRequestController::class, 'declineRequest']); // Additional route
    Route::delete('/hire-requests/{id}', [HireRequestController::class, 'cancelRequest']);
    Route::delete('/hire-request/{id}', [HireRequestController::class, 'cancelRequest']); // Additional route
    Route::get('/hire-requests', [HireRequestController::class, 'listRequests']);
    Route::get('/hire-request', [HireRequestController::class, 'listRequests']); // Additional route
    
    // New Hire Request payment endpoints
    Route::post('/hire/initiate-payment', [HireRequestController::class, 'initiatePayment']);
    Route::get('/hire/verify-payment', [HireRequestController::class, 'verifyPayment'])->name('hire.payment.verify');
    Route::post('/hire/sessions/{id}/end', [HireRequestController::class, 'initiateSessionEnd']);

    // Setup routes
    Route::post('/setup', [SetupController::class, 'setup']);
    Route::get('/preferences', [SetupController::class, 'createPreferences']);
    Route::post('/preferences', [SetupController::class, 'savePreferences']);
    Route::get('/follow-options', [SetupController::class, 'followOptions']);
    Route::get('/setup-status', [SetupController::class, 'checkSetupStatus']);

    // Course routes - add both singular and plural
    Route::post('/course', [CoursesController::class, 'createCourse']); // Additional route
    Route::post('/courses', [CoursesController::class, 'createCourse']);
    Route::get('/course/{id}', [CoursesController::class, 'viewCourse']); // Already there
    Route::get('/courses/{id}', [CoursesController::class, 'viewCourse']);
    Route::get('/course/{id}/content', [CoursesController::class, 'getCourseContent']); // Additional route
    Route::get('/courses/{id}/content', [CoursesController::class, 'getCourseContent']);
    Route::get('/course', [CoursesController::class, 'listCourses']); // Additional route
    Route::get('/courses', [CoursesController::class, 'listCourses']);
    Route::put('/course/{id}', [CoursesController::class, 'updateCourse']); // Additional route
    Route::put('/courses/{id}', [CoursesController::class, 'updateCourse']);
    Route::delete('/course/{id}', [CoursesController::class, 'deleteCourse']); // Additional route
    Route::delete('/courses/{id}', [CoursesController::class, 'deleteCourse']);
    Route::get('/course-by-topic', [CoursesController::class, 'getCoursesByTopic']); // Additional route
    Route::get('/courses-by-topic', [CoursesController::class, 'getCoursesByTopic']);

    // Course Section routes
    Route::get('/courses/{courseId}/sections', [CourseSectionController::class, 'index']);
    Route::get('/course/{courseId}/sections', [CourseSectionController::class, 'index']); // Additional route
    Route::post('/courses/{courseId}/sections', [CourseSectionController::class, 'store']);
    Route::post('/course/{courseId}/sections', [CourseSectionController::class, 'store']); // Additional route
    Route::get('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'show']);
    Route::get('/course/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'show']); // Additional route
    Route::put('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'update']);
    Route::put('/course/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'update']); // Additional route
    Route::delete('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'destroy']);
    Route::delete('/course/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'destroy']); // Additional route

    // Course Lesson routes
    Route::post('/sections/{sectionId}/lessons', [CourseLessonController::class, 'store']);
    Route::post('/section/{sectionId}/lessons', [CourseLessonController::class, 'store']); // Additional route
    Route::post('/section/{sectionId}/lesson', [CourseLessonController::class, 'store']); // Additional route
    Route::post('/sections/{sectionId}/lesson', [CourseLessonController::class, 'store']); // Additional route
    Route::get('/lessons/{lessonId}', [CourseLessonController::class, 'show']);
    Route::get('/lesson/{lessonId}', [CourseLessonController::class, 'show']); // Additional route
    Route::put('/lessons/{lessonId}', [CourseLessonController::class, 'update']);
    Route::put('/lesson/{lessonId}', [CourseLessonController::class, 'update']); // Additional route
    Route::delete('/lessons/{lessonId}', [CourseLessonController::class, 'destroy']);
    Route::delete('/lesson/{lessonId}', [CourseLessonController::class, 'destroy']); // Additional route
    Route::post('/lessons/{lessonId}/complete', [CourseLessonController::class, 'markComplete']);
    Route::post('/lesson/{lessonId}/complete', [CourseLessonController::class, 'markComplete']); // Additional route

    // Live Class routes - both singular and plural
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

    // Duplicate routes with singular "live-class"
    Route::prefix('live-classes')->group(function () {
        Route::get('/', [LiveClassController::class, 'index']);
        Route::post('/', [LiveClassController::class, 'store']);
        Route::get('/{liveClass}', [LiveClassController::class, 'show']);
        Route::put('/{liveClass}', [LiveClassController::class, 'update']);
        Route::delete('/{liveClass}', [LiveClassController::class, 'destroy']);
        
        // Class Participation
        Route::post('/{liveClass}/join', [LiveClassController::class, 'join']);
        Route::post('/{liveClass}/leave', [LiveClassController::class, 'leave']);
        Route::post('/{liveClass}/end', [LiveClassController::class, 'end']);
        
        // WebRTC Signaling
        Route::post('/{classId}/signal', [LiveClassController::class, 'signal']);
        Route::post('/{classId}/ice-candidate', [LiveClassController::class, 'sendIceCandidate']);
        
        // Stream Control
        Route::post('/{liveClass}/start-stream', [LiveClassController::class, 'startStream']);
        Route::post('/{liveClass}/stop-stream', [LiveClassController::class, 'stopStream']);
        
        // Status and Settings
        Route::get('/{liveClass}/status', [LiveClassController::class, 'getRoomStatus']);
        Route::post('/{liveClass}/settings', [LiveClassController::class, 'updateParticipantSettings']);
        
        // User-specific Classes
        Route::get('/my-classes', [LiveClassController::class, 'getMyClasses']);
        Route::get('/enrolled-classes', [LiveClassController::class, 'getEnrolledClasses']);
        
        // Course-related Classes
        Route::get('/course/{courseId}', [LiveClassController::class, 'getClassesForCourse']);
    });
    
    // Live Class Chat
    Route::prefix('live-class-chat')->group(function () {
        Route::post('/{classId}/send', [LiveClassChatController::class, 'sendMessage']);
        Route::get('/{classId}/history', [LiveClassChatController::class, 'getChatHistory']);
        Route::delete('/message/{messageId}', [LiveClassChatController::class, 'deleteMessage']);
    });
    
    // Subscription Check
    Route::get('/check-subscription', [LiveClassController::class, 'checkSubscriptionStatus']);

    Route::get('/files', [App\Http\Controllers\FileController::class, 'index'])->name('files.index');
    Route::get('/files/download', [App\Http\Controllers\FileController::class, 'download'])->name('files.download');
    Route::get('/posts/{postId}/view-file', [FileController::class, 'viewPostFile'])->name('posts.view-file');
    Route::get('/posts/{postId}/download-file', [FileController::class, 'downloadPostFile'])->name('posts.download-file');

    // Educator routes
    Route::post('/create/course', [EducatorsController::class, 'createCourse']);
    Route::get('/dashboard/courses', [EducatorsController::class, 'showEducatorCourses']);
    Route::get('/course/{id}', [EducatorsController::class, 'view']);
    Route::get('/download/{file}', [EducatorsController::class, 'download']);
    Route::get('/educators', [EducatorsController::class, 'getAllEducators']);
    Route::get('/educator', [EducatorsController::class, 'getAllEducators']); // Additional route
    Route::get('/educators/with-follow-status', [EducatorsController::class, 'getAllEducatorsWithFollowStatus']);
    Route::get('/educator/with-follow-status', [EducatorsController::class, 'getAllEducatorsWithFollowStatus']); // Additional route

    // Enrollment routes
    Route::post('/courses/{courseId}/enroll', [EnrollmentController::class, 'enrollInCourse']);
    Route::post('/course/{courseId}/enroll', [EnrollmentController::class, 'enrollInCourse']); // Additional route
    Route::post('/enrollment/verify', [EnrollmentController::class, 'verifyEnrollment'])->name('enrollment.verify');
    Route::get('/enrollments', [EnrollmentController::class, 'getUserEnrollments']);
    Route::get('/enrollment', [EnrollmentController::class, 'getUserEnrollments']); // Additional route
    Route::post('/enrollments/{enrollmentId}/progress', [EnrollmentController::class, 'updateProgress']);
    Route::post('/enrollment/{enrollmentId}/progress', [EnrollmentController::class, 'updateProgress']); // Additional route
    Route::post('/courses/{courseId}/enroll/saved-card', [EnrollmentController::class, 'enrollWithSavedCard']);
    Route::post('/course/{courseId}/enroll/saved-card', [EnrollmentController::class, 'enrollWithSavedCard']); // Additional route

    // Bookmark routes
    Route::post('/bookmark/course/{courseId}', [BookmarkController::class, 'bookmarkCourse']);
    Route::post('/bookmark/post/{lessonId}', [BookmarkController::class, 'bookmarkPost']);
    Route::delete('/bookmark/course/{courseId}', [BookmarkController::class, 'removeBookmarkCourse']);
    Route::delete('/bookmark/post/{lessonId}', [BookmarkController::class, 'removeBookmarkPost']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'getNotifications']);
    Route::get('/notification', [NotificationController::class, 'getNotifications']); // Additional route
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notification/{id}/read', [NotificationController::class, 'markAsRead']); // Additional route

    // Messaging routes
    Route::get('/conversations', [MessageController::class, 'getConversations']);
    Route::get('/conversation', [MessageController::class, 'getConversations']); // Additional route
    Route::get('/conversations/{id}', [MessageController::class, 'getConversation']);
    Route::get('/conversation/{id}', [MessageController::class, 'getConversation']); // Additional route
    Route::post('/messages', [MessageController::class, 'sendMessage']);
    Route::post('/message', [MessageController::class, 'sendMessage']); // Additional route
    Route::post('/conversations/{conversationId}/read', [MessageController::class, 'markAsRead']);
    Route::post('/conversation/{conversationId}/read', [MessageController::class, 'markAsRead']); // Additional route
    Route::delete('/messages/{messageId}', [MessageController::class, 'deleteMessage']);
    Route::delete('/message/{messageId}', [MessageController::class, 'deleteMessage']); // Additional route
    Route::get('/messages/unread-count', [MessageController::class, 'getUnreadCount']);
    Route::get('/message/unread-count', [MessageController::class, 'getUnreadCount']); // Additional route

    // Readlist routes
    Route::get('/readlists', [ReadlistController::class, 'getUserReadlists']);
    Route::get('/readlist', [ReadlistController::class, 'getUserReadlists']); // Additional route
    Route::post('/readlists', [ReadlistController::class, 'store']);
    Route::post('/readlist', [ReadlistController::class, 'store']); // Additional route
    Route::get('/readlists/{id}', [ReadlistController::class, 'show']);
    Route::get('/readlist/{id}', [ReadlistController::class, 'show']); // Additional route
    Route::put('/readlists/{id}', [ReadlistController::class, 'update']);
    Route::put('/readlist/{id}', [ReadlistController::class, 'update']); // Additional route
    Route::delete('/readlists/{id}', [ReadlistController::class, 'destroy']);
    Route::delete('/readlist/{id}', [ReadlistController::class, 'destroy']); // Additional route
    Route::post('/readlists/{id}/items', [ReadlistController::class, 'addItem']);
    Route::post('/readlist/{id}/items', [ReadlistController::class, 'addItem']); // Additional route
    Route::post('/readlist/{id}/item', [ReadlistController::class, 'addItem']); // Additional route
    Route::post('/readlists/{id}/item', [ReadlistController::class, 'addItem']); // Additional route
    Route::delete('/readlists/{id}/items/{itemId}', [ReadlistController::class, 'removeItem']);
    Route::delete('/readlist/{id}/items/{itemId}', [ReadlistController::class, 'removeItem']); // Additional route
    Route::delete('/readlist/{id}/item/{itemId}', [ReadlistController::class, 'removeItem']); // Additional route
    Route::delete('/readlists/{id}/item/{itemId}', [ReadlistController::class, 'removeItem']); // Additional route
    Route::post('/readlists/{id}/reorder', [ReadlistController::class, 'reorderItems']);
    Route::post('/readlist/{id}/reorder', [ReadlistController::class, 'reorderItems']); // Additional route
    Route::post('/readlists/{id}/regenerate-key', [ReadlistController::class, 'regenerateShareKey']);
    Route::post('/readlist/{id}/regenerate-key', [ReadlistController::class, 'regenerateShareKey']); // Additional route

    // Open Library routes
    Route::get('/libraries', [OpenLibraryController::class, 'index']);
    Route::get('/library', [OpenLibraryController::class, 'index']); // Additional route
    Route::post('/libraries', [OpenLibraryController::class, 'store']);
    Route::post('/library', [OpenLibraryController::class, 'store']); // Additional route
    Route::get('/libraries/{id}', [OpenLibraryController::class, 'show']);
    Route::get('/library/{id}', [OpenLibraryController::class, 'show']); // Additional route
    Route::put('/libraries/{id}', [OpenLibraryController::class, 'update']);
    Route::put('/library/{id}', [OpenLibraryController::class, 'update']); // Additional route
    Route::delete('/libraries/{id}', [OpenLibraryController::class, 'destroy']);
    Route::delete('/library/{id}', [OpenLibraryController::class, 'destroy']); // Additional route
    Route::post('/libraries/{id}/refresh', [OpenLibraryController::class, 'refreshLibrary']);
    Route::post('/library/{id}/refresh', [OpenLibraryController::class, 'refreshLibrary']); // Additional route
    Route::post('/libraries/{id}/content', [OpenLibraryController::class, 'addContent']);
    Route::post('/library/{id}/content', [OpenLibraryController::class, 'addContent']); // Additional route
    Route::delete('/libraries/{id}/content', [OpenLibraryController::class, 'removeContent']);
    Route::delete('/library/{id}/content', [OpenLibraryController::class, 'removeContent']); // Additional route
    Route::post('/libraries/dynamic', [OpenLibraryController::class, 'createDynamicLibrary']);
    Route::post('/library/dynamic', [OpenLibraryController::class, 'createDynamicLibrary']); // Additional route
    Route::get('/courses/{courseId}/libraries', [OpenLibraryController::class, 'getCourseLibraries']);
    Route::get('/course/{courseId}/libraries', [OpenLibraryController::class, 'getCourseLibraries']); // Additional route
    Route::get('/courses/{courseId}/library', [OpenLibraryController::class, 'getCourseLibraries']); // Additional route
    Route::get('/course/{courseId}/library', [OpenLibraryController::class, 'getCourseLibraries']); // Additional route
    Route::get('/libraries/similar', [OpenLibraryController::class, 'getSimilarLibraries']);
    Route::get('/library/similar', [OpenLibraryController::class, 'getSimilarLibraries']); // Additional route

    // Cogni AI Assistant routes
    Route::post('/cogni/ask', [CogniController::class, 'ask']);
    Route::get('/cogni/conversations', [CogniController::class, 'getConversations']);
    Route::get('/cogni/conversation', [CogniController::class, 'getConversations']); // Additional route
    Route::get('/cogni/conversations/{conversationId}', [CogniController::class, 'getConversationHistory']);
    Route::get('/cogni/conversation/{conversationId}', [CogniController::class, 'getConversationHistory']); // Additional route
    Route::post('/cogni/explain', [CogniController::class, 'explain']);
    Route::post('/cogni/generate-quiz', [CogniController::class, 'generateQuiz']);
    Route::post('/cogni/generate-readlist', [CogniController::class, 'generateTopicReadlist']);
    Route::post('/cogni/conversations/clear', [CogniController::class, 'clearConversation']);
    Route::post('/cogni/conversation/clear', [CogniController::class, 'clearConversation']); // Additional route

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
    Route::post('/subscription/initiate', [PaystackController::class, 'initiateSubscription']); // Additional route
    Route::get('/subscriptions/verify/{reference}', [PaystackController::class, 'verifyPayment']);
    Route::get('/subscription/verify/{reference}', [PaystackController::class, 'verifyPayment']); // Additional route
    Route::post('/subscriptions/cancel', [PaystackController::class, 'cancelSubscription']);
    Route::post('/subscription/cancel', [PaystackController::class, 'cancelSubscription']); // Additional route
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::get('/subscription', [SubscriptionController::class, 'index']); // Additional route
    Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
    Route::get('/subscription/current', [SubscriptionController::class, 'current']); // Additional route
    Route::get('/subscriptions/upcoming', [SubscriptionController::class, 'upcoming']);
    Route::get('/subscription/upcoming', [SubscriptionController::class, 'upcoming']); // Additional route
    Route::get('/subscriptions/history', [SubscriptionController::class, 'history']);
    Route::get('/subscription/history', [SubscriptionController::class, 'history']); // Additional route
    Route::post('/subscriptions/free', [PaystackController::class, 'createFreeSubscription']);
    Route::post('/subscription/free', [PaystackController::class, 'createFreeSubscription']); // Additional route
    Route::post('/payment/retry', [PaystackController::class, 'retryPayment']);

    // Tutoring
    Route::post('/tutoring/request', [HireRequestController::class, 'sendRequest']);
    Route::get('/tutoring/requests', [HireRequestController::class, 'listRequests']);
    Route::get('/tutoring/requests/{id}', [HireRequestController::class, 'getRequest']);
    Route::post('/tutoring/requests/{id}/accept', [HireRequestController::class, 'acceptRequest']);
    Route::post('/tutoring/requests/{id}/decline', [HireRequestController::class, 'declineRequest']);
    Route::delete('/tutoring/requests/{id}', [HireRequestController::class, 'cancelRequest']);
    
    // Session management
    Route::post('/tutoring/requests/{id}/schedule', [HireRequestController::class, 'scheduleSession']);
    Route::post('/tutoring/sessions/{id}/payment', [HireRequestController::class, 'processPayment']);
    Route::get('/tutoring/sessions/{id}/payment/verify', [HireRequestController::class, 'verifyPayment'])->name('tutoring.payment.verify');
    Route::post('/tutoring/sessions/{id}/complete', [HireRequestController::class, 'completeSession']);
    
    // Hire Sessions and Ratings
    Route::prefix('hire-sessions')->group(function () {
        Route::get('/', [HireSessionController::class, 'index']);
        Route::get('/{id}', [HireSessionController::class, 'show']);
        Route::post('/{id}/complete', [HireSessionController::class, 'complete']);
        Route::post('/{id}/rate', [HireSessionController::class, 'rateEducator']);
    });
    
    // Payment methods routes - add both singular and plural
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index']);
        Route::post('/initiate', [PaymentMethodController::class, 'initiate']);
        Route::post('/{id}/default', [PaymentMethodController::class, 'setDefault']);
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy']);
    });
    
    Route::prefix('payment-method')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index']);
        Route::post('/initiate', [PaymentMethodController::class, 'initiate']);
        Route::post('/{id}/default', [PaymentMethodController::class, 'setDefault']);
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy']);
    });
});

// Webhook routes (no authentication required)
Route::post('/webhooks/paystack', [PaystackController::class, 'handleWebhook']);
Route::post('/webhook/paystack', [PaystackController::class, 'handleWebhook']); // Additional route
Route::post('/webhooks/enrollment', [EnrollmentController::class, 'handleWebhook']);
Route::post('/webhook/enrollment', [EnrollmentController::class, 'handleWebhook']); // Additional route

// Public readlist share route
Route::get('/readlists/shared/{shareKey}', [ReadlistController::class, 'showByShareKey']);
Route::get('/readlist/shared/{shareKey}', [ReadlistController::class, 'showByShareKey']); // Additional route

// Public payment verification route
Route::get('/payment-methods/verify', [PaymentMethodController::class, 'verify'])->name('payment-methods.verify');
Route::get('/payment-method/verify', [PaymentMethodController::class, 'verify'])->name('payment-method.verify'); // Additional route

// Success and failure routes for payment methods and subscriptions
Route::get('/payment-methods/success', function() {
    return view('payment-methods.success');
})->name('payment-methods.success');

Route::get('/payment-method/success', function() {
    return view('payment-methods.success');
})->name('payment-method.success'); // Additional route

Route::get('/payment-methods/failed', function() {
    return view('payment-methods.failed');
})->name('payment-methods.failed');

Route::get('/payment-method/failed', function() {
    return view('payment-methods.failed');
})->name('payment-method.failed'); // Additional route

Route::get('/subscription/success', function() {
    return view('subscription.success');
})->name('subscription.success');

Route::get('/subscriptions/success', function() {
    return view('subscription.success');
})->name('subscriptions.success'); // Additional route

Route::get('/subscription/failed', function() {
    return view('subscription.failed');
})->name('subscription.failed');

Route::get('/subscriptions/failed', function() {
    return view('subscription.failed');
})->name('subscriptions.failed'); // Additional route

// Device registration for push notifications
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/device/register', [\App\Http\Controllers\DeviceController::class, 'registerDevice']);
    Route::post('/device/unregister', [\App\Http\Controllers\DeviceController::class, 'unregisterDevice']);
    
    // Push notification endpoints
    Route::post('/notifications/broadcast', [NotificationController::class, 'broadcastPushNotification']);
    Route::post('/notification/broadcast', [NotificationController::class, 'broadcastPushNotification']); // Additional route
    Route::post('/notifications/send', [NotificationController::class, 'sendPushNotification']);
    Route::post('/notification/send', [NotificationController::class, 'sendPushNotification']); // Additional route
    Route::get('/notifications/debug-apns', [NotificationController::class, 'debugApns']);
});