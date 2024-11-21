<?php

use App\Http\Controllers\CoursesController;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthManager;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ProfileController;

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
Route::post('register', [AuthManager::class, 'register'])->name('register');
Route::post('login', [AuthManager::class, 'login'])->name('login');
Route::post('resetPassReq', [AuthManager::class, 'resetPasswordRequest'])->name('resetPassReq');
Route::post('resetPassword', [AuthManager::class, 'resetPassword'])->name('resetPassword');


// Protected routes
Route::prefix('api')->middleware(['auth:sanctum'])->group(function() {
    //logout routes
    Route::post('logout', [AuthManager::class, 'logout'])->name('logout');
    Route::post('logoutProd', [AuthManager::class, 'logoutProd'])->name('logoutProd');

    //profile routes
    Route::get('profile/{user:username}', [ProfileController::class, 'viewProfile'])->name('profile.view');

    //account setup route
    Route::post('setup', [SetupController::class, 'setup'])->name('setup');

    //Courses route
    Route::post('create-course', [CoursesController::class, 'postCourse'])->name('postCourse');
});
