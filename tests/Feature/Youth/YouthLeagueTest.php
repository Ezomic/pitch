<?php

declare(strict_types=1);

use App\Actions\Season\AdvanceWeek;
use App\Actions\Season\EnsureSeason;
use App\Actions\Season\PlayMatchday;
use App\Actions\Season\Standings;
use App\Actions\Youth\BuildYouthTeam;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Team::factory()->count(3)->create(['is_youth' => false]);
    Team::factory()->count(4)->create(['is_youth' => true]);
});

it('schedules a youth fixture against each youth side and keeps the leagues apart', function () {
    $season = app(EnsureSeason::class)->handle(User::factory()->create());

    expect($season->fixtures()->where('youth', true)->count())->toBe(4);

    // No senior fixture references a youth team, and no youth fixture a senior team.
    $youthIds = Team::query()->where('is_youth', true)->pluck('id');
    $seniorFixtureTeams = $season->fixtures()->where('youth', false)
        ->get()->flatMap(fn ($f) => [$f->home_team_id, $f->away_team_id])->filter();

    expect($seniorFixtureTeams->intersect($youthIds))->toBeEmpty();
});

it('does not play youth fixtures on the senior matchday', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);

    app(PlayMatchday::class)->handle($season);

    expect($season->fixtures()->where('youth', true)->where('played', true)->count())->toBe(0)
        ->and($season->fixtures()->where('youth', false)->where('played', true)->count())->toBeGreaterThan(0);
});

it('plays the due youth fixture when the week advances and fills the youth table', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);

    app(AdvanceWeek::class)->handle($season);

    expect($season->fixtures()->where('youth', true)->where('played', true)->count())->toBe(1);

    $table = app(Standings::class)->handle($season->fresh(), youth: true);
    expect($table)->toHaveCount(5) // 4 youth teams + the academy
        ->and(array_sum(array_column($table, 'played')))->toBe(2)
        ->and(collect($table)->firstWhere('isUser', true))->not->toBeNull();
});

it('fields the strongest prospects first and caps the youth XI at eleven', function () {
    $user = User::factory()->create();
    Player::factory()->youth($user->id)->count(12)->create();
    $gem = Player::factory()->youth($user->id)->create([
        'vision' => 90, 'passing' => 90, 'dribbling' => 90, 'finishing' => 90, 'tackling' => 90, 'pace' => 90,
    ]);

    $featured = app(BuildYouthTeam::class)->featured($user);

    expect($featured)->toHaveCount(10)
        ->and($featured->first()->id)->toBe($gem->id);
});

function attributeSum(Player $player): int
{
    return $player->vision + $player->passing + $player->dribbling
        + $player->finishing + $player->tackling + $player->pace;
}

it('gives featured prospects an extra week of development from match minutes', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $prospect = Player::factory()->youth($user->id)->create([
        'potential' => 100, 'vision' => 30, 'passing' => 30, 'dribbling' => 30, 'finishing' => 30, 'tackling' => 30, 'pace' => 30,
    ]);
    $before = attributeSum($prospect);

    app(AdvanceWeek::class)->handle($season); // weekly develop + a youth match this week

    expect(attributeSum($prospect->refresh()) - $before)->toBe(10);
});

it('shows the youth league on the academy page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('youth.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Youth')
            ->has('leagueTable', 5)
            ->has('fixtures', 4),
        );
});
