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
use App\Http\Controllers\CogniController;
use App\Http\Controllers\OpenLibraryController;

Route::post('/register', [AuthManager::class, 'register'])->name('register');
Route::post('/login', [AuthManager::class, 'login'])->name('login');
Route::post('/forgotPassword', [AuthManager::class, 'forgorPassword'])->name('resetPassReq');
Route::post('/resetPassword', [AuthManager::class, 'resetPassword'])->name('resetPassword');
Route::post('/setup', [SetupController::class, 'setup'])->name('setup');
Route::post('/createPreferences', [SetupController::class, 'createPreferences'])->name('createPreferences');
Route::get('/followOptions', [SetupController::class, 'followOptions'])->name('followOptions');
Route::get('/educators', [EducatorsController::class, 'getAllEducators']);
Route::prefix('live-class')->group(function () {
    Route::get('/', [LiveClassController::class, 'index']);
    Route::get('/{id}', [LiveClassController::class, 'show']);
});
Route::get('/readlists/shared/{shareKey}', [ReadlistController::class, 'showByShareKey']);
Route::middleware('auth:sanctum')->group(function() {
    Route::prefix('live-class')->group(function () {
        Route::post('/', [LiveClassController::class, 'store']);
        Route::post('/{id}/join', [LiveClassController::class, 'join']);
        Route::post('/{id}/end', [LiveClassController::class, 'end']);
        Route::post('/{id}/signal', [LiveClassController::class, 'signal']);
        Route::get('/{id}/participants', [LiveClassController::class, 'getParticipants']);
        Route::post('/{id}/start-stream', [LiveClassController::class, 'startStream']);
        Route::post('/{id}/stop-stream', [LiveClassController::class, 'stopStream']);
        Route::get('/{id}/stream-info', [LiveClassController::class, 'getStreamInfo']);
        Route::post('/{id}/ice-candidate', [LiveClassController::class, 'sendIceCandidate']);
        Route::get('/{id}/room-status', [LiveClassController::class, 'getRoomStatus']);
        Route::post('/{id}/participant-settings', [LiveClassController::class, 'updateParticipantSettings']);
        Route::post('/{id}/connection-quality', [LiveClassController::class, 'reportConnectionQuality']);
    });

    // Cogni endpoints
    Route::middleware('auth:sanctum')->prefix('cogni')->group(function () {
        // Original Cogni routes
        Route::post('/ask', [App\Http\Controllers\EnhancedCogniController::class, 'ask']);
        Route::get('/conversations', [App\Http\Controllers\CogniController::class, 'getConversations']);
        Route::get('/conversations/{conversationId}', [App\Http\Controllers\CogniController::class, 'getConversationHistory']);
        Route::post('/conversations/clear', [App\Http\Controllers\CogniController::class, 'clearConversation']);
        
        // New Readlist related endpoints
        Route::post('/readlists/generate', [App\Http\Controllers\EnhancedCogniController::class, 'generateReadlist']);
        Route::get('/readlists/{id}/analyze', [App\Http\Controllers\EnhancedCogniController::class, 'analyzeReadlist']);
        Route::get('/readlists/{id}/recommend', [App\Http\Controllers\EnhancedCogniController::class, 'recommendForReadlist']);
        Route::get('/readlists/{id}/assessments', [App\Http\Controllers\EnhancedCogniController::class, 'generateAssessments']);
        Route::post('/readlists/{id}/plan', [App\Http\Controllers\EnhancedCogniController::class, 'createStudyPlan']);
        
        // Educator recommendations
        Route::get('/educators/recommend', [App\Http\Controllers\EnhancedCogniController::class, 'recommendEducators']);
    });
    
    // Open Library Routes
    Route::get('/libraries', [OpenLibraryController::class, 'index']);
    Route::post('/libraries', [OpenLibraryController::class, 'store']);
    Route::post('/libraries/dynamic', [OpenLibraryController::class, 'createDynamicLibrary']);
    Route::get('/libraries/{id}', [OpenLibraryController::class, 'show']);
    Route::put('/libraries/{id}', [OpenLibraryController::class, 'update']);
    Route::delete('/libraries/{id}', [OpenLibraryController::class, 'destroy']);
    Route::post('/libraries/{id}/refresh', [OpenLibraryController::class, 'refreshLibrary']);
    Route::post('/libraries/{id}/content', [OpenLibraryController::class, 'addContent']);
    Route::delete('/libraries/{id}/content', [OpenLibraryController::class, 'removeContent']);
    Route::get('/courses/{courseId}/libraries', [OpenLibraryController::class, 'getCourseLibraries']);
    Route::get('/libraries/similar', [OpenLibraryController::class, 'getSimilarLibraries']);
    
    Route::get('/educators/with-follows', [EducatorsController::class, 'getAllEducatorsWithFollowStatus']);
    Route::post('/logout', [AuthManager::class, 'logout'])->name('logout');
    Route::get('/feed', [FeedController::class, 'feed'])->name('feed');
    Route::get('/user/{id}', [AuthManager::class, 'fetchUser'])->name('user');
    Route::get('/profile/{user:username}', [ProfileController::class, 'viewProfile'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'UploadAvatar'])->name('profile.uploadAvatar');
    Route::post('/createPref', [SetupController::class, 'createPref'])->name('createPref');
    Route::post('/savePref', [SetupController::class, 'savePreferences'])->name('savePref');
    Route::post('/follow/{id}', [FollowController::class, 'createFollow'])->name('createFollow');
    Route::post('/unfollow/{id}', [FollowController::class, 'unFollow'])->name('unfollow');
    Route::post('/create-course', [EducatorsController::class, 'createCourse'])->name('postCourse');
    Route::get('/course/{id}', [EducatorsController::class, 'view'])->name('view');
    Route::post('/courses', [CoursesController::class, 'createCourse']);
    Route::get('/courses', [CoursesController::class, 'listCourses']);
    Route::get('/courses/{id}', [CoursesController::class, 'viewCourse']);
    Route::put('/courses/{id}', [CoursesController::class, 'updateCourse']);
    Route::delete('/courses/{id}', [CoursesController::class, 'deleteCourse']);
    Route::get('/courses/{id}/content', [CoursesController::class, 'getCourseContent']);
    Route::get('/courses/{courseId}/sections', [CourseSectionController::class, 'index']);
    Route::post('/courses/{courseId}/sections', [CourseSectionController::class, 'store']);
    Route::get('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'show']);
    Route::put('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'update']);
    Route::delete('/courses/{courseId}/sections/{sectionId}', [CourseSectionController::class, 'destroy']);
    Route::post('/sections/{sectionId}/lessons', [CourseLessonController::class, 'store']);
    Route::get('/lessons/{lessonId}', [CourseLessonController::class, 'show']);
    Route::put('/lessons/{lessonId}', [CourseLessonController::class, 'update']);
    Route::delete('/lessons/{lessonId}', [CourseLessonController::class, 'destroy']);
    Route::post('/lessons/{lessonId}/complete', [CourseLessonController::class, 'markComplete']);
    Route::get('/courses-by-topic', [CoursesController::class, 'getCoursesByTopic']);
    Route::post('/post', [PostController::class, 'storePost'])->name('post');
    Route::get('/viewPost', [PostController::class, 'viewSinglePost'])->name('viewPost');
    Route::delete('/posts/{post}', [PostController::class, 'deletePost'])->name('post.delete');
    Route::post('/post/{post}/comment', [CommentController::class, 'postComment'])->name('post.comment');
    Route::get('/posts/{post}/comments', [CommentController::class, 'displayComments']);
    Route::get('/posts/{postId}/comment-count', [CommentController::class, 'getCommentCount']);
    Route::get('/posts/{postId}/stats', [PostController::class, 'getPostStats']);
    Route::post('/post/{post}/like', [LikeController::class, 'createLike'])->name('like.post');
    Route::get('/post_likes/{postId}', [LikeController::class, 'post_like_count']);
    Route::get('/comment_likes/{commentId}', [LikeController::class, 'comment_like_count']);
    Route::get('/course_likes/{courseId}', [LikeController::class, 'course_like_count']);
    Route::post('/comment/{comment}/like', [LikeController::class, 'createLike'])->name('like.comment');
    Route::post('/course/{course}/like', [LikeController::class, 'createLike'])->name('like.course');
    Route::post('/course/{courseId}/bookmark', [BookmarkController::class, 'bookmarkCourse']);
    Route::post('/post/{lessonId}/bookmark', [BookmarkController::class, 'bookmarkPost']);
    Route::delete('/course/{courseId}/bookmark', [BookmarkController::class, 'removeBookmarkCourse']);
    Route::delete('/post/{lessonId}/bookmark', [BookmarkController::class, 'removeBookmarkPost']);
    Route::prefix('readlists')->group(function () {
        Route::get('/', [ReadlistController::class, 'index']);
        Route::post('/', [ReadlistController::class, 'store']);
        Route::get('/user', [ReadlistController::class, 'getUserReadlists']);
        Route::get('/public', [ReadlistController::class, 'publicReadlists']);
        Route::get('/{id}', [ReadlistController::class, 'show']);
        Route::put('/{id}', [ReadlistController::class, 'update']);
        Route::delete('/{id}', [ReadlistController::class, 'destroy']);
        Route::post('/{id}/items', [ReadlistController::class, 'addItem']);
        Route::delete('/{id}/items/{itemId}', [ReadlistController::class, 'removeItem']);
        Route::put('/{id}/reorder', [ReadlistController::class, 'reorderItems']);
        Route::post('/{id}/regenerate-share-key', [ReadlistController::class, 'regenerateShareKey']);
    });
    Route::get('/search', [SearchController::class, 'search'])->name('search');
    Route::post('/hire-request', [HireRequestController::class, 'sendRequest']);
    Route::patch('/hire-request/{id}/accept', [HireRequestController::class, 'acceptRequest']);
    Route::patch('/hire-request/{id}/decline', [HireRequestController::class, 'declineRequest']);
    Route::get('/hire-requests', [HireRequestController::class, 'listRequests']);
    Route::delete('/hire-requests/{id}', [HireRequestController::class, 'cancelRequest']);
    Route::get('/notifications', [NotificationController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/setup_status', [SetupController::class, 'checkSetupStatus']);
    Route::post('/send-chat-message', function(Request $request) {
        $formFields = $request->validate([
            'textvalue' => 'required'
        ]);
        if (!trim(strip_tags($formFields['textvalue']))) {
            return response()->noContent();
        }
        broadcast(new ChatMessage([
            'username' => auth()->user()->username,
            'textvalue' => strip_tags($request->textvalue),
            'avatar' => auth()->user()->avatar
        ]))->toOthers();
        return response()->noContent();
    });
    Route::prefix('messages')->group(function () {
        Route::get('/conversations', [MessageController::class, 'getConversations']);
        Route::get('/conversations/{id}', [MessageController::class, 'getConversation']);
        Route::post('/send', [MessageController::class, 'sendMessage']);
        Route::post('/conversations/{id}/read', [MessageController::class, 'markAsRead']);
        Route::delete('/messages/{id}', [MessageController::class, 'deleteMessage']);
        Route::get('/unread-count', [MessageController::class, 'getUnreadCount']);
    });
    Route::prefix('subscription')->group(function () {
        Route::post('/initiate', [PaystackController::class, 'initiateSubscription']);
        Route::get('/verify/{reference}', [PaystackController::class, 'verifyPayment']);
        Route::get('/', [SubscriptionController::class, 'index']);
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::get('/upcoming', [SubscriptionController::class, 'upcoming']);
        Route::get('/history', [SubscriptionController::class, 'history']);
    });
    Route::get('/live-class/subscription-status', [LiveClassController::class, 'checkSubscriptionStatus']);
    Route::post('/enroll/{courseId}', [EnrollmentController::class, 'enrollInCourse']);
    Route::get('/enrollments', [EnrollmentController::class, 'getUserEnrollments']);
    Route::post('/enrollments/{id}/progress', [EnrollmentController::class, 'updateProgress']);
});
Route::post('/paystack/webhook', [PaystackController::class, 'handleWebhook']);
Route::post('/subscription/free', [PaystackController::class, 'createFreeSubscription']);
Route::post('/enrollment/verify', [EnrollmentController::class, 'verifyEnrollment'])->name('enrollment.verify');
Route::post('/enrollment/webhook', [EnrollmentController::class, 'handleWebhook']);