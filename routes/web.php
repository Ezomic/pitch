<?php

use App\Http\Controllers\Auth\LoginCodeController;
use App\Http\Controllers\CupController;
use App\Http\Controllers\LiveMatchController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\ScoutController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\SquadController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\YouthController;
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
    Route::get('squad/what-if', [SquadController::class, 'whatIf'])->name('squad.what-if');
    Route::get('squad/compare', [SquadController::class, 'compare'])->name('squad.compare');
    Route::patch('squad/slot', [SquadController::class, 'assign'])->name('squad.assign');
    Route::patch('squad/tactics', [SquadController::class, 'tactics'])->name('squad.tactics');
    Route::patch('squad/role', [SquadController::class, 'role'])->name('squad.role');
    Route::patch('squad/keeper', [SquadController::class, 'keeper'])->name('squad.keeper');
    Route::patch('squad/formation', [SquadController::class, 'customize'])->name('squad.customize');

    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::post('transfers/{player}/sign', [TransferController::class, 'sign'])->name('transfers.sign');
    Route::post('transfers/{player}/sell', [TransferController::class, 'sell'])->name('transfers.sell');
    Route::post('transfers/{player}/renew', [TransferController::class, 'renew'])->name('transfers.renew');

    Route::get('match', [MatchController::class, 'show'])->name('match.show');
    Route::get('match/live/{fixture}', [LiveMatchController::class, 'show'])->name('match.live.show');
    Route::post('match/live/{fixture}/bench', [LiveMatchController::class, 'bench'])->name('match.live.bench');
    Route::post('match/live/{fixture}/sub', [LiveMatchController::class, 'sub'])->name('match.live.sub');
    Route::post('match/live/{fixture}/finish', [LiveMatchController::class, 'finish'])->name('match.live.finish');

    Route::get('season', [SeasonController::class, 'show'])->name('season.show');
    Route::get('cup', [CupController::class, 'show'])->name('cup.show');
    Route::get('news', [NewsController::class, 'index'])->name('news.index');
    Route::post('news/{news}/accept', [NewsController::class, 'accept'])->name('news.accept');
    Route::post('news/{news}/decline', [NewsController::class, 'decline'])->name('news.decline');
    Route::post('season/advance', [SeasonController::class, 'advance'])->name('season.advance');
    Route::post('season/reset', [SeasonController::class, 'reset'])->name('season.reset');
    Route::post('season/rollover', [SeasonController::class, 'rollover'])->name('season.rollover');
    Route::get('season/fixtures/{fixture}/report', [SeasonController::class, 'report'])->name('season.report');
    Route::get('season/fixtures/{fixture}/scout', [SeasonController::class, 'scout'])->name('season.scout');

    Route::get('scouts', [ScoutController::class, 'index'])->name('scouts.index');
    Route::post('scouts/{scout}/hire', [ScoutController::class, 'hire'])->name('scouts.hire');
    Route::post('scouts/{scout}/assign', [ScoutController::class, 'assign'])->name('scouts.assign');
    Route::post('scouts/{scout}/recall', [ScoutController::class, 'recall'])->name('scouts.recall');

    Route::get('youth', [YouthController::class, 'index'])->name('youth.index');
    Route::post('youth/{player}/promote', [YouthController::class, 'promote'])->name('youth.promote');
    Route::post('youth/{player}/loan', [YouthController::class, 'loan'])->name('youth.loan');
    Route::post('youth/{player}/recall', [YouthController::class, 'recall'])->name('youth.recall');
    Route::patch('youth/{player}/focus', [YouthController::class, 'focus'])->name('youth.focus');
});

require __DIR__.'/settings.php';
