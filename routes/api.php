<?php

use App\Http\Controllers\AuthManager;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\ForgotPasswordManager;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResetPasswordController;
use Illuminate\Support\Facades\Route;

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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

//tidying up

Route::group(['prefix' => 'auth'], function () {
	Route::post('register', [AuthManager::class, 'register']);
	Route::post('login', [AuthManager::class, 'login']);
	Route::post('resetPassReq', [AuthManager::class, 'resetPasswordRequest']);
	Route::post('resetPassword', [AuthManager::class, 'resetPassword']);
	Route::post('forgot-Password', 'ForgotPasswordManager@forgotPassword');

	Route::group(['middleware' => 'auth:sanctum'], function () {
		Route::get('/profile/{user:username}', 'ProfileController@showProfile');
		Route::get('change-password', 'ForgotPasswordManager@changePassword');
		Route::post('update-profile', 'ProfileController@update');
		Route::post('uploadAvatar', 'ProfileController@UploadAvatar');
		Route::post('/create-follow/{user:username}', 'FollowController@createFollow');
		Route::post('/unfollow/{user:username}', 'FollowController@unfollow');
		Route::post('/PostCourse', 'CoursesController@PostCourse');
		Route::get('/courses/{id}', 'CoursesController@showCourse');
		Route::post('/updateCourse', 'CoursesController@updateCourse');
		Route::delete('/courses/{id}', 'CoursesController@deleteCourse');
		Route::post('/comment', 'CommentController@postComment');

	});
});

//forgotPass/resetpass routes
Route::post('forgot-Password', [ForgotPasswordManager::class, 'forgotPassword'])->name('forgot.password.post');
Route::post('resetPassword', [ResetPasswordController::class, 'resetPassword'])->name('reset.password');

//navigation
//Route::post('/', [AuthManager::class, "showCorrecthomepage"])->name('login');

//Profile related routes
Route::get('/profile/{user:username}', [ProfileController::class, 'showProfile']); //->middleware('checkAuth');
Route::post('/uploadAvatar', [ProfileController::class, 'UploadAvatar'])->middleware('mustBeLoggedIn');

//follow related routes
Route::post('/create-follow/{user:username}', [FollowController::class, 'createFollow'])->middleware('mustBeLoggedIn');
Route::post('/unfollow/{user:username}', [FollowController::class, 'unFollow'])->middleware('mustBeLoggedIn');

// Blog post related routes
Route::post('/storeNewPost', [PostController::class, 'storeNewPost']);
Route::get('/post/{post}', [PostController::class, 'viewSinglePost']);

//courses
//post a course
Route::post('/PostCourse', [CoursesController::class, 'PostCourse'])->middleware('mustBeLoggedIn');
// Show a specific course
Route::get('/courses/{id}', [CoursesController::class, 'showCourse']);
//update course
Route::post('/updateCourse', [CoursesController::class, 'updateCourse'])->middleware('mustBeLoggedIn');

// Delete a specific course
Route::delete('/courses/{id}', [CoursesController::class, 'deleteCourse'])->middleware('mustBeLoggedIn');

//Commenting on posts
Route::post('/comment', [CommentController::class, 'postComment']);

/*Route::post('/Upload', [EducatorsController::class, 'upload'])->middleware('mustBeLoggedIn');
Route::post('/download{file}', [EducatorsController::class, 'download'])->middleware('mustBeLoggedIn');
Route::get('/show', [EducatorsController::class, 'show']);
 */
