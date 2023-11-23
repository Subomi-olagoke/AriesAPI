<?php


use App\Http\Controllers\ResetPasswordController;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthManager;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EducatorsController;
use App\Http\Controllers\ForgotPasswordManager;
use App\Http\Controllers\PasswordResetController;

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
