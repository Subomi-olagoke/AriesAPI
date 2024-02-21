<?php

use Laravel\Sanctum\Sanctum;
use App\Http\Controllers\AuthManager;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

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

//tidying up




	Route::post('register', [AuthManager::class, 'register']);
	Route::post('login', [AuthManager::class, 'login'])->name('login');
	Route::post('resetPassReq', [AuthManager::class, 'resetPasswordRequest']);
	Route::post('resetPassword', [AuthManager::class, 'resetPassword']);
    Route::get('/profile/{user:username}', [AuthManager::class, 'profile']);
    Route::middleware('auth:sanctum')->group(function() {
        Route::post('/post', [PostController::class, 'storePost'])->middleware('auth:sanctum');
        Route::post('/comment', [CommentController::class, 'postComment']);
    });

	//Route::group(['middleware' => 'auth:sanctum'], function () {

	// 	Route::get('/profile', [AuthManager::class, 'profile'])->middleware(['auth:sanctum']);

	// 	Route::group(['middleware' => 'ability:user,admin'], function () {

	// 		Route::get('change-password', 'ForgotPasswordManager@changePassword');
	// 		Route::post('update-profile', 'ProfileController@update');
	// 		Route::post('uploadAvatar', 'ProfileController@UploadAvatar');
	// 		Route::post('/create-follow/{user:username}', 'FollowController@createFollow');
	// 		Route::post('/unfollow/{user:username}', 'FollowController@unfollow');
	// 		Route::post('/PostCourse', 'CoursesController@PostCourse');
	// 		Route::get('/courses/{id}', 'CoursesController@showCourse');
	// 		Route::post('/updateCourse', 'CoursesController@updateCourse');
	// 		Route::delete('/courses/{id}', 'CoursesController@deleteCourse');
	// 		Route::post('/comment', 'CommentController@postComment');

	// 	});
