<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every live match holds a whole serialised engine state, so they are cleared
// out rather than left to pile up.
Schedule::command('pitch:prune-live-matches')->dailyAt('04:00');
