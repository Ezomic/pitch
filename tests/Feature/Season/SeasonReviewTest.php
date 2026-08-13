<?php

declare(strict_types=1);

use App\Actions\Season\SeasonReview;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $strong = ['vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60];
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Defender]);
    Player::factory()->count(5)->create([...$strong, 'position' => Position::Midfielder]);
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Forward]);
    Team::factory()->count(7)->create(['is_youth' => false]);
});

/**
 * @param  array<int, array{name: string, appearances: int, goals: int, ratingSum: float}>  $players
 */
function seasonWithReview(User $user, array $players): Season
{
    return Season::create([
        'user_id' => $user->id,
        'number' => 1,
        'starts_on' => Season::STARTS_ON,
        'current_date' => Season::STARTS_ON,
        'review' => ['players' => $players],
    ]);
}

/**
 * @return array{target: int, position: int, teams: int, met: bool|null}
 */
function objectiveOf(int $position, int $teams = 8, ?bool $met = true): array
{
    return ['target' => intdiv($teams, 2), 'position' => $position, 'teams' => $teams, 'met' => $met];
}

it('names the top scorer, the most used player and the best of them', function () {
    $user = User::factory()->create();
    $season = seasonWithReview($user, [
        1 => ['name' => 'Alice', 'appearances' => 10, 'goals' => 12, 'ratingSum' => 72.0],
        2 => ['name' => 'Bob', 'appearances' => 20, 'goals' => 3, 'ratingSum' => 150.0],
        3 => ['name' => 'Cara', 'appearances' => 2, 'goals' => 0, 'ratingSum' => 19.6],
    ]);

    $review = (new SeasonReview)->handle($season, [], objectiveOf(3));

    expect($review['topScorers'][0])->toBe(['name' => 'Alice', 'goals' => 12])
        ->and($review['mostAppearances'][0])->toBe(['name' => 'Bob', 'appearances' => 20])
        // Bob averages 7.5 over a full season; Cara averages 9.8 over two games.
        ->and($review['playerOfTheSeason']['name'])->toBe('Bob');
});

it('will not crown someone on the strength of one good afternoon', function () {
    $user = User::factory()->create();
    $season = seasonWithReview($user, [
        1 => ['name' => 'Cameo', 'appearances' => 1, 'goals' => 3, 'ratingSum' => 10.0],
    ]);

    $review = (new SeasonReview)->handle($season, [], objectiveOf(4));

    expect($review['playerOfTheSeason'])->toBeNull()
        ->and($review['topScorers'][0]['name'])->toBe('Cameo');
});

it('states the board verdict plainly, either way', function () {
    $user = User::factory()->create();
    $season = seasonWithReview($user, []);
    $reviewer = new SeasonReview;

    $won = $reviewer->handle($season, [], objectiveOf(1));
    $missed = $reviewer->handle($season, [], objectiveOf(7, 8, false));
    $running = $reviewer->handle($season, [], objectiveOf(3, 8, null));

    expect($won['verdict'])->toContain('Champions')
        ->and($missed['verdict'])->toContain('short of')
        ->and($running['verdict'])->toContain('not over');
});

it('copes with a season nobody played a match in', function () {
    $user = User::factory()->create();
    $review = (new SeasonReview)->handle(seasonWithReview($user, []), [], objectiveOf(5));

    expect($review['topScorers'])->toBe([])
        ->and($review['mostAppearances'])->toBe([])
        ->and($review['playerOfTheSeason'])->toBeNull();
});

it('builds the record as fixtures finish, and shows it once the season is done', function () {
    $user = User::factory()->create();
    $season = Season::create([
        'user_id' => $user->id, 'number' => 1,
        'starts_on' => Season::STARTS_ON, 'current_date' => Season::STARTS_ON,
    ]);
    $team = Team::query()->where('is_youth', false)->first();
    $fixture = $season->fixtures()->create([
        'matchday' => 1, 'scheduled_on' => Season::STARTS_ON,
        'home_team_id' => null, 'away_team_id' => $team->id, 'seed' => 99, 'played' => false,
    ]);

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    for ($i = 0; $i < 40; $i++) {
        $body = $this->actingAs($user)
            ->postJson(route('play.advance', $matchId), ['ticks' => 120])->json();

        if ($body['finished']) {
            break;
        }
    }

    $season->refresh();
    $fixture->refresh();

    expect($fixture->played)->toBeTrue()
        ->and($season->review)->not->toBeNull()
        ->and($season->review['players'])->not->toBeEmpty();

    // Everyone who appeared has one appearance from that single fixture.
    foreach ($season->review['players'] as $row) {
        expect($row['appearances'])->toBe(1)
            ->and($row['name'])->not->toBeEmpty();
    }
});

it('shows no review while the season is still being played', function () {
    $user = User::factory()->create();

    $props = $this->actingAs($user)->get(route('season.show'))
        ->assertOk()->viewData('page')['props'];

    expect($props['review'])->toBeNull();
});
