<?php

use Illuminate\Http\Request;
use App\Http\Controllers\AuthManager;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;

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
Route::post('register', [AuthManager::class, 'register']);
Route::post('login', [AuthManager::class, 'login'])->name('login');
Route::post('resetPassReq', [AuthManager::class, 'resetPasswordRequest']);
Route::post('resetPassword', [AuthManager::class, 'resetPassword']);
//Route::get('/profile/{user:username}', [AuthManager::class, 'profile']);

// Protected routes
Route::middleware(['auth:sanctum'])->group(function() {
    Route::post('/post', [PostController::class, 'storePost']);
    Route::post('/comment', [CommentController::class, 'postComment']);
    Route::post('/like', [LikeController::class, 'likePost']);
    Route::post('/like', [LikeController::class, 'likeComment']);
    Route::delete('/like', [LikeController::class, 'removeLikeComment']);
    Route::delete('/like', [LikeController::class, 'removeLikePost']);
    Route::get('/like', [LikeController::class, 'displayLikes']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
