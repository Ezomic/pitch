<?php

use App\Http\Controllers\Auth\LoginCodeController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\ScoutController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\SquadController;
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

    Route::get('squad', [SquadController::class, 'edit'])->name('squad.edit');
    Route::patch('squad/slot', [SquadController::class, 'assign'])->name('squad.assign');
    Route::patch('squad/tactics', [SquadController::class, 'tactics'])->name('squad.tactics');

    Route::get('match', [MatchController::class, 'show'])->name('match.show');

    Route::get('season', [SeasonController::class, 'show'])->name('season.show');
    Route::post('season/advance', [SeasonController::class, 'advance'])->name('season.advance');
    Route::post('season/reset', [SeasonController::class, 'reset'])->name('season.reset');
    Route::get('season/fixtures/{fixture}/report', [SeasonController::class, 'report'])->name('season.report');

    Route::get('scouts', [ScoutController::class, 'index'])->name('scouts.index');
    Route::post('scouts/{scout}/hire', [ScoutController::class, 'hire'])->name('scouts.hire');
    Route::post('scouts/{scout}/assign', [ScoutController::class, 'assign'])->name('scouts.assign');
    Route::post('scouts/{scout}/recall', [ScoutController::class, 'recall'])->name('scouts.recall');
});

require __DIR__.'/settings.php';
