<?php

use Illuminate\Support\Facades\Route;

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

// Route::get('/', function () {
//     return view('welcome');
// });

Route::middleware(['auth'])->group(function () {
    Route::get('/live-class/{id}', [LiveClassController::class, 'show'])->name('live-class.show');
    Route::post('/live-class', [LiveClassController::class, 'store'])->name('live-class.store');
    Route::post('/live-class/{id}/join', [LiveClassController::class, 'join'])->name('live-class.join');
    Route::post('/live-class/{id}/end', [LiveClassController::class, 'end'])->name('live-class.end');
});
