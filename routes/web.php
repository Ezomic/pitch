<?php

use App\Http\Controllers\Auth\LoginCodeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::post('login/code', [LoginCodeController::class, 'send'])
        ->middleware('throttle:login')
        ->name('login.code.send');

    Route::post('login/code/verify', [LoginCodeController::class, 'verify'])
        ->middleware('throttle:login')
        ->name('login.code.verify');
});

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
