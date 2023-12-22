<?php

use App\Http\Controllers\AuthManager;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EducatorsController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\ForgotPasswordManager;
use App\Http\Controllers\livestreamController;
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

//Authentication routes
Route::post('Register', [AuthManager::class, 'register'])->name('register.post');
Route::any('Educator.reg', [EducatorsController::class, 'register'])->name('Educator.reg');
Route::post('EducatorLogin', [AuthManager::class, 'login']);
Route::post('login', [AuthManager::class, 'login']);

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
Route::post('/storeNewPost', [PostController::class, 'storeNewPost'])->middleware('mustBeLoggedIn');
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

//livestream
Route::get('/test', [livestreamController::class, 'someAction']);
