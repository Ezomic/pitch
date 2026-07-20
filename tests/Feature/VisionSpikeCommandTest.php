<?php

declare(strict_types=1);

use App\Models\MatchEvent;
use App\Models\MatchResult;
use App\Models\SimulationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists a run, sampled matches, and their events', function () {
    $this->artisan('pitch:vision-spike', [
        '--matches' => 20,
        '--sample' => 3,
        '--seed' => 1,
    ])->assertSuccessful();

    expect(SimulationRun::count())->toBe(1)
        ->and(MatchResult::count())->toBe(6)
        ->and(MatchEvent::count())->toBeGreaterThan(0);

    $run = SimulationRun::first();
    expect($run->separated)->toBeTrue()
        ->and($run->matches)->toBe(20)
        ->and($run->report)->toHaveKey('deltas');

    $event = MatchEvent::whereNotNull('decision')->first();
    expect($event->decision)->toHaveKeys(['options_visible', 'options_total', 'chosen_threat', 'best_available_threat']);
});

it('can run without persisting', function () {
    $this->artisan('pitch:vision-spike', [
        '--matches' => 20,
        '--no-persist' => true,
    ])->assertSuccessful();

    expect(SimulationRun::count())->toBe(0);
});
