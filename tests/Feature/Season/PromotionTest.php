<?php

declare(strict_types=1);

use App\Actions\Season\PromoteRelegate;
use App\Actions\Season\ScheduleSeason;
use App\Actions\Squad\EnsureSquad;
use App\Models\Fixture;
use App\Models\Season;
use App\Models\Squad;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Give both divisions rivals so a move up or down has somewhere to land. */
function seedDivisions(): void
{
    Team::factory()->count(3)->create(['is_youth' => false, 'division' => 1]);
    Team::factory()->count(3)->create(['is_youth' => false, 'division' => 2]);
}

function completedSeasonFor(User $user, int $division): Season
{
    $season = Season::create([
        'user_id' => $user->id,
        'number' => 1,
        'division' => $division,
        'starts_on' => Season::STARTS_ON,
        'current_date' => Season::STARTS_ON,
        'completed_at' => now(),
    ]);

    app(ScheduleSeason::class)->handle($season);

    return $season;
}

/** Win every senior fixture the user is in; lose the rest to a chosen wooden-spoon team. */
function decideTable(Season $season, bool $userWins): void
{
    foreach ($season->fixtures()->where('youth', false)->get() as $fixture) {
        if (! $fixture->home_team_id || ! $fixture->away_team_id) {
            $userHome = $fixture->home_team_id === null;
            $fixture->update([
                'home_goals' => $userHome === $userWins ? 3 : 0,
                'away_goals' => $userHome === $userWins ? 0 : 3,
                'played' => true,
            ]);

            continue;
        }

        $fixture->update(['home_goals' => 1, 'away_goals' => 1, 'played' => true]);
    }
}

it('schedules the user only against teams in their division', function () {
    seedDivisions();
    $user = User::factory()->create();
    $season = completedSeasonFor($user, 2);

    $opponentIds = $season->fixtures()->where('youth', false)
        ->get()
        ->flatMap(fn (Fixture $f) => [$f->home_team_id, $f->away_team_id])
        ->filter()->unique();

    $divisionTwoIds = Team::query()->where('division', 2)->pluck('id');
    $divisionOneIds = Team::query()->where('division', 1)->pluck('id');

    expect($opponentIds->diff($divisionTwoIds))->toBeEmpty()
        ->and($opponentIds->intersect($divisionOneIds))->toBeEmpty();
});

it('promotes a division winner to the tier above', function () {
    seedDivisions();
    $user = User::factory()->create();
    $squad = app(EnsureSquad::class)->handle($user);
    $squad->forceFill(['division' => 2])->save();
    $season = completedSeasonFor($user, 2);
    decideTable($season, userWins: true);

    $outcome = app(PromoteRelegate::class)->handle($squad, $season);

    expect($outcome)->toBe('promoted')
        ->and($squad->refresh()->division)->toBe(1);
});

it('relegates a bottom side to the tier below', function () {
    seedDivisions();
    $user = User::factory()->create();
    $squad = app(EnsureSquad::class)->handle($user);
    $squad->forceFill(['division' => 1])->save();
    $season = completedSeasonFor($user, 1);
    decideTable($season, userWins: false);

    $outcome = app(PromoteRelegate::class)->handle($squad, $season);

    expect($outcome)->toBe('relegated')
        ->and($squad->refresh()->division)->toBe(2);
});

it('does not promote beyond the top division', function () {
    seedDivisions();
    $user = User::factory()->create();
    $squad = app(EnsureSquad::class)->handle($user);
    $squad->forceFill(['division' => 1])->save();
    $season = completedSeasonFor($user, 1);
    decideTable($season, userWins: true);

    $outcome = app(PromoteRelegate::class)->handle($squad, $season);

    expect($outcome)->toBe('stayed')
        ->and($squad->refresh()->division)->toBe(Squad::TOP_DIVISION);
});
