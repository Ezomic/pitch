<?php

declare(strict_types=1);

use App\Actions\LiveSim\FinishFixture;
use App\Actions\LiveSim\ReplayMatch;
use App\Models\Fixture;
use App\Models\LiveMatch;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadPlayer;
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
});

function injuryFixtureFor(User $user): Fixture
{
    $season = Season::create([
        'user_id' => $user->id,
        'number' => 1,
        'starts_on' => Season::STARTS_ON,
        'current_date' => Season::STARTS_ON,
    ]);
    $team = Team::factory()->create(['is_youth' => false]);

    return $season->fixtures()->create([
        'matchday' => 1,
        'scheduled_on' => Season::STARTS_ON,
        'home_team_id' => null,
        'away_team_id' => $team->id,
        'seed' => 4242,
        'played' => false,
    ]);
}

/** Make one player certain to be hurt on the first tick, in the stored state and the kickoff it replays from. */
function makeBrittle(LiveMatch $match, int $side, int $slot, int $weeks): void
{
    foreach (['pitch_state', 'kickoff_state'] as $column) {
        $state = $match->{$column};
        foreach ($state['players'] as &$player) {
            if ((int) $player['side'] === $side && (int) $player['slot'] === $slot) {
                $player['pr'] = 0.5;
                $player['sn'] = 1.0;
                $player['wk'] = $weeks;
            }
        }
        unset($player);
        $match->{$column} = $state;
    }
    $match->save();
}

/** @return array{LiveMatch, User} */
function injuryStarted(bool $league): array
{
    $user = User::factory()->create();
    if ($league) {
        injuryFixtureFor($user);
    }

    $matchId = test()->actingAs($user)->get(route('play.show'))->viewData('page')['props']['matchId'];

    return [LiveMatch::query()->findOrFail($matchId), $user];
}

function injuryAdvance(User $user, LiveMatch $match, int $ticks): array
{
    return test()->actingAs($user)->postJson(route('play.advance', $match->id), ['ticks' => $ticks])->assertOk()->json();
}

function injuryPlayOut(User $user, LiveMatch $match): void
{
    for ($i = 0; $i < 40; $i++) {
        if (injuryAdvance($user, $match, 120)['finished']) {
            return;
        }
    }
}

function injurySlotPlayer(LiveMatch $match, int $slot): int
{
    foreach ($match->fresh()->players as $meta) {
        if ((int) $meta['s'] === 0 && (int) $meta['slot'] === $slot) {
            return (int) $meta['pid'];
        }
    }

    throw new RuntimeException("No player in slot {$slot}");
}

it('flags a hurt player in the live match and puts the injury in the feed', function () {
    [$match, $user] = injuryStarted(league: false);
    $props = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];

    expect($props['hurt'])->toBe([])
        ->and($match->pitch_state['subsLeft'])->toBe([5, 5]);

    makeBrittle($match, side: 0, slot: 3, weeks: 4);
    $body = injuryAdvance($user, $match, 30);

    $kinds = array_column($body['moments'], 'kind');

    expect($body['hurt'])->toBe([3 => 'limping'])
        ->and($kinds)->toContain('injury')
        ->and($this->actingAs($user)->get(route('play.show'))->viewData('page')['props']['hurt'])->toBe([3 => 'limping']);
});

it('records who was hurt and for how long, and the opposition not at all', function () {
    [$match, $user] = injuryStarted(league: false);
    makeBrittle($match, side: 0, slot: 3, weeks: 4);
    makeBrittle($match, side: 1, slot: 5, weeks: 6);

    injuryAdvance($user, $match, 30);
    $injuries = $match->fresh()->injuries;

    expect($injuries)->toHaveCount(1)
        ->and($injuries[0]['pid'])->toBe(injurySlotPlayer($match, 3))
        ->and($injuries[0]['weeks'])->toBe(4);
});

it('carries the injury into the season at full time and drops him from the XI', function () {
    [$match, $user] = injuryStarted(league: true);
    $pid = injurySlotPlayer($match, 3);
    makeBrittle($match, side: 0, slot: 3, weeks: 4);

    injuryPlayOut($user, $match);

    expect(Player::query()->findOrFail($pid)->injured_weeks)->toBe(4)
        ->and(SquadPlayer::query()->where('player_id', $pid)->exists())->toBeFalse();
});

it('does not cost a friendly any weeks', function () {
    [$match, $user] = injuryStarted(league: false);
    $pid = injurySlotPlayer($match, 3);
    makeBrittle($match, side: 0, slot: 3, weeks: 6);

    injuryPlayOut($user, $match);

    expect($match->fresh()->injuries)->toHaveCount(1)
        ->and(Player::query()->findOrFail($pid)->injured_weeks)->toBe(0)
        ->and(SquadPlayer::query()->where('player_id', $pid)->exists())->toBeTrue();
});

it('applies a match injury once, however often full time is settled', function () {
    [$match, $user] = injuryStarted(league: true);
    $pid = injurySlotPlayer($match, 3);
    makeBrittle($match, side: 0, slot: 3, weeks: 5);

    injuryPlayOut($user, $match);
    expect(Player::query()->findOrFail($pid)->injured_weeks)->toBe(5);

    Player::query()->whereKey($pid)->update(['injured_weeks' => 2]);

    app(FinishFixture::class)->handle($match->fresh());
    injuryAdvance($user, $match, 120);

    expect(Player::query()->findOrFail($pid)->injured_weeks)->toBe(2);
});

it('keeps a longer absence that was already running', function () {
    [$match, $user] = injuryStarted(league: true);
    $pid = injurySlotPlayer($match, 3);
    Player::query()->whereKey($pid)->update(['injured_weeks' => 7]);
    makeBrittle($match, side: 0, slot: 3, weeks: 2);

    injuryPlayOut($user, $match);

    expect(Player::query()->findOrFail($pid)->injured_weeks)->toBe(7);
});

it('still carries the injury of a man who was substituted before full time', function () {
    [$match, $user] = injuryStarted(league: true);
    $props = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];
    $hurtId = injurySlotPlayer($match, 3);
    makeBrittle($match, side: 0, slot: 3, weeks: 3);

    injuryAdvance($user, $match, 30);
    $this->actingAs($user)->postJson(route('play.sub', $match->id), [
        'out_slot' => 3,
        'player_id' => $props['bench'][0]['id'],
    ])->assertOk();

    $state = $match->fresh()->pitch_state;
    $slot = collect($state['players'])->first(fn (array $p): bool => $p['side'] === 0 && $p['slot'] === 3);

    expect($slot['inj'])->toBeFalse()
        ->and($slot['sn'])->toEqual(0)
        ->and($state['subsLeft'])->toBe([4, 5])
        ->and($match->fresh()->subs_remaining)->toBe(4)
        ->and($this->actingAs($user)->get(route('play.show'))->viewData('page')['props']['hurt'])->toBe([]);

    $subbedOn = injurySlotPlayer($match, 3);

    injuryPlayOut($user, $match);

    expect(Player::query()->findOrFail($hurtId)->injured_weeks)->toBe(3)
        ->and(Player::query()->findOrFail($subbedOn)->injured_weeks)->toBe(0);
});

it('can hurt a substitute too, and carries both injuries', function () {
    [$match, $user] = injuryStarted(league: true);
    $props = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];
    $hurtId = injurySlotPlayer($match, 3);
    makeBrittle($match, side: 0, slot: 3, weeks: 3);

    injuryAdvance($user, $match, 30);
    $this->actingAs($user)->postJson(route('play.sub', $match->id), [
        'out_slot' => 3,
        'player_id' => $props['bench'][0]['id'],
    ])->assertOk();
    $subbedOn = injurySlotPlayer($match, 3);
    makeBrittle($match->fresh(), side: 0, slot: 3, weeks: 3);

    injuryAdvance($user, $match, 30);
    injuryPlayOut($user, $match);

    expect($match->fresh()->injuries)->toHaveCount(2)
        ->and(Player::query()->findOrFail($hurtId)->injured_weeks)->toBe(3)
        ->and(Player::query()->findOrFail($subbedOn)->injured_weeks)->toBe(3);
});

it('replays a match with an injury and a substitution to the same score and frames', function () {
    [$match, $user] = injuryStarted(league: true);
    $props = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];
    makeBrittle($match, side: 0, slot: 3, weeks: 3);
    makeBrittle($match, side: 1, slot: 6, weeks: 2);

    injuryAdvance($user, $match, 90);
    $this->actingAs($user)->postJson(route('play.sub', $match->id), [
        'out_slot' => 3,
        'player_id' => $props['bench'][0]['id'],
    ])->assertOk();
    injuryPlayOut($user, $match);

    $finished = LiveMatch::query()->findOrFail($match->id);
    $first = app(ReplayMatch::class)->handle($finished);
    $second = app(ReplayMatch::class)->handle($finished);

    expect($first['homeGoals'])->toBe($finished->home_goals)
        ->and($first['awayGoals'])->toBe($finished->away_goals)
        ->and($first['frames'])->toEqual($second['frames']);
});

it('has the opposition replace a hurt man itself, until it runs out of changes', function () {
    [$match, $user] = injuryStarted(league: false);
    makeBrittle($match, side: 1, slot: 5, weeks: 2);

    $body = injuryAdvance($user, $match, 30);
    $state = $match->fresh()->pitch_state;
    $theirs = collect($state['players'])->first(fn (array $p): bool => $p['side'] === 1 && $p['slot'] === 5);

    expect($theirs['ret'])->toBeFalse()
        ->and($theirs['inj'])->toBeFalse()
        ->and($state['subsLeft'])->toBe([5, 4])
        ->and($state['autoSubs'])->toBe([false, true])
        ->and($body['hurt'])->toBe([]);
});

it('plays the opposition a man down when it has no change left', function () {
    [$match, $user] = injuryStarted(league: false);
    $state = $match->pitch_state;
    $state['subsLeft'] = [5, 0];
    $match->update(['pitch_state' => $state]);
    makeBrittle($match->fresh(), side: 1, slot: 5, weeks: 2);

    injuryAdvance($user, $match, 30);
    $theirs = collect($match->fresh()->pitch_state['players'])->first(fn (array $p): bool => $p['side'] === 1 && $p['slot'] === 5);

    expect($theirs['ret'])->toBeTrue();
});
