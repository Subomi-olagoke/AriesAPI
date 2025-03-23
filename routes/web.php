<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AuthManager;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Auth routes
Route::get('/login', [AuthManager::class, 'login'])->name('login');
Route::get('/register', [AuthManager::class, 'register'])->name('register');
Route::get('/forgot-password', [AuthManager::class, 'forgotPassword'])->name('forgot-password');
Route::get('/reset-password', [AuthManager::class, 'resetPassword'])->name('reset-password');

// Email verification routes
Route::prefix('email')->group(function () {
    Route::get('/verify', [AuthManager::class, 'verifyEmail'])->name('verification.notice');
    Route::get('/verify/{id}/{hash}', [AuthManager::class, '__invoke'])->middleware(['signed'])->name('verification.verify');
    Route::post('/verification-notification', [AuthManager::class, 'resendVerificationEmail'])->middleware(['throttle:6,1'])->name('verification.send');
});

// Payment success/failure pages
Route::view('/payment-methods/success', 'payment-methods.success')->name('payment-methods.success');
Route::view('/payment-methods/failed', 'payment-methods.failed')->name('payment-methods.failed');

// Subscription success/failure pages
Route::view('/subscription/success', 'subscription.success')->name('subscription.success');
Route::view('/subscription/failed', 'subscription.failed')->name('subscription.failed');

// Enrollment success/failure pages
Route::view('/enrollment/success', 'enrollment.success')->name('enrollment.success');
Route::view('/enrollment/failed', 'enrollment.failed')->name('enrollment.failed');