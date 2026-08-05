<?php

declare(strict_types=1);

use App\Models\LiveMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

function liveMatchAged(User $user, string $status, int $daysOld): LiveMatch
{
    $match = LiveMatch::create([
        'user_id' => $user->id,
        'seed' => 1,
        'current_tick' => 0,
        'total_ticks' => 2700,
        'pitch_state' => [],
        'rng_state' => 1,
        'home_goals' => 0,
        'away_goals' => 0,
        'home_name' => 'Your squad',
        'away_name' => 'Opposition',
        'players' => [],
        'moments' => [],
        'subs_remaining' => 5,
        'status' => $status,
    ]);

    $match->forceFill(['updated_at' => Date::now()->subDays($daysOld)])->saveQuietly();

    return $match;
}

it('clears abandoned matches at once and stale ones after the window', function () {
    $user = User::factory()->create();

    $abandoned = liveMatchAged($user, LiveMatch::ABANDONED, 0);
    $staleFinished = liveMatchAged($user, LiveMatch::FINISHED, 30);
    $staleLive = liveMatchAged($user, LiveMatch::LIVE, 30);
    $recentFinished = liveMatchAged($user, LiveMatch::FINISHED, 1);
    $recentLive = liveMatchAged($user, LiveMatch::LIVE, 1);

    $this->artisan('pitch:prune-live-matches')->assertSuccessful();

    expect(LiveMatch::query()->find($abandoned->id))->toBeNull()
        ->and(LiveMatch::query()->find($staleFinished->id))->toBeNull()
        ->and(LiveMatch::query()->find($staleLive->id))->toBeNull()
        // A manager who stepped away mid-match can still come back to it.
        ->and(LiveMatch::query()->find($recentLive->id))->not->toBeNull()
        ->and(LiveMatch::query()->find($recentFinished->id))->not->toBeNull();
});

it('honours a custom window', function () {
    $user = User::factory()->create();
    $match = liveMatchAged($user, LiveMatch::LIVE, 3);

    $this->artisan('pitch:prune-live-matches', ['--days' => 10])->assertSuccessful();
    expect(LiveMatch::query()->find($match->id))->not->toBeNull();

    $this->artisan('pitch:prune-live-matches', ['--days' => 2])->assertSuccessful();
    expect(LiveMatch::query()->find($match->id))->toBeNull();
});
