<?php

use App\Http\Controllers\AuthManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ForgotPasswordManager;

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
Route::post('login', [AuthManager::class, 'login']);

//forgotPass/resetpass routes
Route::post('forgot-Password', [ForgotPasswordManager::class, 'forgotPassword'])->name('forgot.password.post');
Route::post('reset-password/{token}', [ForgotPasswordManager::class, 'resetPassword'])->name('reset.password');
Route::post('reset-passwordPost', [ForgotPasswordManager::class, 'resetPasswordPost'])->name('reset.password.post');

//navigation
//Route::post('/', [AuthManager::class, "showCorrecthomepage"])->name('login');
