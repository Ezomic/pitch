<?php

declare(strict_types=1);

use App\Actions\Season\DrawCup;
use App\Actions\Season\EnsureSeason;
use App\Actions\Season\PlayCupRound;
use App\Actions\Squad\EnsureSquad;
use App\Models\CupTie;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seasonWithCup(int $rivals = 7): array
{
    $user = User::factory()->create();
    Team::factory()->count($rivals)->create(['is_youth' => false]);
    app(EnsureSquad::class)->handle($user);
    $season = app(EnsureSeason::class)->handle($user);

    return [$user, $season];
}

it('draws an opening round pairing the user with every rival', function () {
    [, $season] = seasonWithCup(7);

    $roundOne = $season->cupTies()->where('round', 1)->get();
    $entrants = $roundOne->flatMap(fn (CupTie $t) => [$t->home, $t->away])->filter()->unique();

    // 8 entrants (user + 7) => 4 ties, and the user is one of the entrants.
    expect($roundOne)->toHaveCount(4)
        ->and($entrants)->toContain(CupTie::USER)
        ->and($entrants)->toHaveCount(8);
});

it('does not redraw a cup that already exists', function () {
    [, $season] = seasonWithCup(7);
    $before = $season->cupTies()->count();

    app(DrawCup::class)->handle($season);

    expect($season->cupTies()->count())->toBe($before);
});

it('resolves one round per call and eventually crowns a champion', function () {
    [, $season] = seasonWithCup(7);

    $guard = 0;
    while ($season->cupTies()->where('played', false)->exists() && $guard++ < 10) {
        app(PlayCupRound::class)->handle($season);
    }

    $finalRound = (int) $season->cupTies()->max('round');
    $finalTies = $season->cupTies()->where('round', $finalRound)->get();

    expect($season->cupTies()->where('played', false)->exists())->toBeFalse()
        ->and($finalTies)->toHaveCount(1)
        ->and($finalTies->first()->winner)->not->toBeNull();
});

it('is deterministic: the same season draws and plays the same cup', function () {
    $user = User::factory()->create();
    Team::factory()->count(7)->create(['is_youth' => false]);
    app(EnsureSquad::class)->handle($user);
    $season = app(EnsureSeason::class)->handle($user);

    while ($season->cupTies()->where('played', false)->exists()) {
        app(PlayCupRound::class)->handle($season);
    }

    $finalRound = (int) $season->cupTies()->max('round');
    $champion = $season->cupTies()->where('round', $finalRound)->first()->winner;

    // The opening tie's seed is fixed to the season, so the draw is reproducible.
    $tie = $season->cupTies()->where('round', 1)->orderBy('slot')->first();
    expect($tie->seed)->toBe($season->id * 1000 + 700)
        ->and($champion)->not->toBeNull();
});

it('handles an odd entrant count with a bye', function () {
    // 6 rivals + user = 7 entrants => one bye in round one.
    [, $season] = seasonWithCup(6);

    $byes = $season->cupTies()->where('round', 1)->get()->filter(fn (CupTie $t) => $t->isBye());

    expect($byes)->toHaveCount(1)
        ->and($byes->first()->played)->toBeTrue()
        ->and($byes->first()->winner)->toBe($byes->first()->home);
});

it('renders the cup bracket page', function () {
    [$user] = seasonWithCup(7);

    $this->actingAs($user)
        ->get(route('cup.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cup')
            ->has('rounds')
            ->has('rounds.0.ties')
            ->has('champion')
            ->has('userOut'),
        );
});
