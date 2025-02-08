<?php

use App\Models\User;
use App\Events\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\AuthManager;
use Illuminate\Support\Facades\Route;
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
    Route::post('/live-class', [LiveClassController::class, 'store']);
    Route::post('/live-class/{id}/join', [LiveClassController::class, 'join']);
    Route::post('/live-class/{id}/end', [LiveClassController::class, 'end']);
    Route::get('/live-class/{id}', [LiveClassController::class, 'show']);

    Route::post('/logout', [AuthManager::class, 'logout'])->name('logout');
    Route::get('/feed', [FeedController::class, 'feed'])->name('feed');
    Route::get('/user{id}', [AuthManager::class, 'fetchUser'])->name('user');

    //profile routes
    Route::get('/profile/{user:username}', [ProfileController::class, 'viewProfile'])->name('profile');

    //user preferences route
    Route::post('/createPref', [SetupController::class, 'createPref'])->name('createPref');
    Route::post('/savePref', [SetupController::class, 'savePreferences'])->name('createPref');

    //follow routes
    Route::post('/follow/{id}', [FollowController::class, 'createFollow'])->name('createFollow');
    Route::post('/unfollow/{id}', [FollowController::class, 'unFollow'])->name('unfollow');

    //Courses route
    Route::post('/create-course', [EducatorsController::class, 'createCourse'])->name('postCourse');
    Route::get('/course/{id}', [EducatorsController::class, 'view'])->name('view');

    //post routes
    Route::post('/post', [PostController::class, 'storePost'])->name('post');
    Route::get('/viewPost', [PostController::class, 'viewSinglePost'])->name('viewPost');

    //comment route
    Route::post('/post/{post}/comment', [CommentController::class, 'postComment'])->name('post.comment');

    //like routes
    Route::post('/post/{post}/like', [LikeController::class, 'createLike'])->name('like.post');
    Route::post('/comment/{comment}/like', [LikeController::class, 'createLike'])->middleware('auth:api')->name('like.comment');
    Route::post('/course/{course}/like', [LikeController::class, 'createLike'])->middleware('auth:api')->name('like.course');

    Route::get('/search', [SearchController::class, 'search'])->name('search');

    Route::post('/hire-request', [HireRequestController::class, 'sendRequest']);
    Route::patch('/hire-request/{id}/accept', [HireRequestController::class, 'acceptRequest']);
    Route::patch('/hire-request/{id}/decline', [HireRequestController::class, 'declineRequest']);
    Route::get('/hire-requests', [HireRequestController::class, 'getRequests']);

    //setup status
    Route::get('/setup_status', [SetupController::class, 'checkSetupStatus']);

    //chat route
    Route::post('/send-chat-message', function(Request $request){
        $formFields = $request->validate([
            'textvalue' => 'required'
        ]);
        if (!trim(strip_tags($formFields['textvalue']))) {
            return response()->noContent();
        }
        broadcast(event: new ChatMessage(
            chat: ['username'=>auth()->user()->username,
            'textvalue'=>strip_tags(string: $request->textvalue),
            'avatar' => auth()->user()->avatar]))->toOthers();
            return response()->noContent();
    })->middleware(middleware: 'mustBeLoggedIn');
});
