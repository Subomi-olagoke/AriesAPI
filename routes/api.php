<?php

use App\Events\ChatMessage;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthManager;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EducatorsController;

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

// Public routes
Route::post('/register', [AuthManager::class, 'register'])->name('register');
Route::post('/login', [AuthManager::class, 'login'])->name('login');
Route::post('/resetPassReq', [AuthManager::class, 'resetPasswordRequest'])->name('resetPassReq');
Route::post('/resetPassword', [AuthManager::class, 'resetPassword'])->name('resetPassword');

//setup routes. Here we setup user preferences
Route::post('/setup', [SetupController::class, 'setup'])->name('setup');
Route::post('/createPreferences', [SetupController::class, 'createPreferences'])->name('createPreferences');
Route::get('/followOptions', [SetupController::class, 'followOptions'])->name('followOptions');


// Protected routes
Route::prefix('api')->middleware(['auth:sanctum'])->group(function() {
    //logout routes
    //Route::post('/logoutTest', [AuthManager::class, 'logoutTest'])->name('logoutTest');
    Route::post('/logout', [AuthManager::class, 'logout'])->name('logout');

    //profile routes
    Route::get('/profile/{user:username}', [ProfileController::class, 'viewProfile'])->name('profile.view');

    //user preferences route
    Route::post('/createPref', [SetupController::class, 'createPref'])->name('createPref');
    Route::post('/savePref', [SetupController::class, 'savePreferences'])->name('createPref');

    //follow routes
    Route::post('/createFollow', [FollowController::class, 'createFollow'])->name('createFollow');
    Route::post('/unfollow', [FollowController::class, 'unFollow'])->name('unfollow');

    //Courses route
    Route::post('/create-course', [EducatorsController::class, 'createCourse'])->name('postCourse');
    Route::get('/course/{id}', [EducatorsController::class, 'view'])->name('view');

    //feed route
    Route::get('/feed', [FeedController::class, 'feed'])->name('feed');

    Route::post('/post', [PostController::class, 'storePost'])->name('post');
    Route::get('/viewPost', [PostController::class, 'viewSinglePost'])->name('viewPost');



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
