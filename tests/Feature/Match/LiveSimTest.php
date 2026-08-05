<?php

declare(strict_types=1);

use App\Models\LiveMatch;
use App\Models\Player;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $strong = ['vision' => 60, 'passing' => 60, 'dribbling' => 60, 'finishing' => 60, 'tackling' => 60, 'pace' => 60];
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Defender]);
    Player::factory()->count(5)->create([...$strong, 'position' => Position::Midfielder]);
    Player::factory()->count(4)->create([...$strong, 'position' => Position::Forward]);
});

it('starts a live positional match with 22 players', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('play.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('LiveSim')
            ->has('matchId')
            ->has('players', 22)
            ->has('totalTicks')
            ->where('subsRemaining', 5));
});

it('advances a live match slice by slice to full time', function () {
    $user = User::factory()->create();

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $finished = false;
    $goals = 0;
    $moments = [];
    for ($i = 0; $i < 40 && ! $finished; $i++) {
        $body = $this->actingAs($user)
            ->postJson(route('play.advance', $matchId), ['ticks' => 300])
            ->assertOk()
            ->json();
        $finished = $body['finished'];
        $goals = $body['homeGoals'] + $body['awayGoals'];
        $moments = [...$moments, ...$body['moments']];
    }

    expect($finished)->toBeTrue()
        ->and($goals)->toBeGreaterThanOrEqual(0)
        ->and($moments)->not->toBeEmpty();

    // Each key moment carries the kind the live view filters highlights by.
    foreach ($moments as $moment) {
        expect($moment)->toHaveKeys(['minute', 'side', 'kind', 'text', 'why']);
    }

    // The decision inspector: at least one moment records what the player saw and
    // the draw that resolved it.
    $inspectable = array_filter($moments, fn (array $m): bool => ($m['why']['decision'] ?? null) !== null);
    expect($inspectable)->not->toBeEmpty();

    $why = array_values($inspectable)[0]['why'];
    expect($why['decision'])->toHaveKeys(['optionsVisible', 'optionsTotal', 'chosenThreat', 'bestThreat', 'gap'])
        ->and($why['roll'])->toHaveKeys(['threshold', 'draw', 'succeeded']);
});

it('changes mentality mid-match', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $this->actingAs($user)
        ->postJson(route('play.mentality', $matchId), ['mentality' => 'attacking'])
        ->assertOk()
        ->assertJson(['mentality' => 'attacking']);
});

it('resumes the match in progress instead of starting another', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    // advance() clamps a slice to 120 ticks, so that is what actually gets played.
    $this->actingAs($user)
        ->postJson(route('play.advance', $first), ['ticks' => 120])
        ->assertOk();

    // A refresh mid-match used to abandon the game and kick off another.
    $page = $this->actingAs($user)->get(route('play.show'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($page['matchId'])->toBe($first)
        ->and($page['currentTick'])->toBe(120)
        ->and(LiveMatch::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('hands the resumed match its score, feed and mentality', function () {
    $user = User::factory()->create();

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $this->actingAs($user)->postJson(route('play.mentality', $matchId), ['mentality' => 'defensive']);
    $this->actingAs($user)->postJson(route('play.advance', $matchId), ['ticks' => 120]);

    $match = LiveMatch::query()->findOrFail($matchId);

    $this->actingAs($user)->get(route('play.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('LiveSim')
            ->where('currentTick', 120)
            ->where('homeGoals', $match->home_goals)
            ->where('awayGoals', $match->away_goals)
            ->where('mentality', 'defensive')
            ->has('moments', count($match->moments)));
});

it('starts a new match only when asked to, abandoning the old one', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $second = $this->actingAs($user)->post(route('play.store'))
        ->assertRedirect(route('play.show'))
        ->getTargetUrl();

    $current = $this->actingAs($user)->get($second)
        ->viewData('page')['props']['matchId'];

    expect($current)->not->toBe($first)
        ->and(LiveMatch::query()->findOrFail($first)->status)->toBe(LiveMatch::ABANDONED)
        ->and(LiveMatch::query()->findOrFail($current)->status)->toBe(LiveMatch::LIVE);
});

it('does not resume someone else\'s match', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $mine = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $theirs = $this->actingAs($other)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    expect($theirs)->not->toBe($mine);

    $this->actingAs($other)
        ->postJson(route('play.advance', $mine), ['ticks' => 30])
        ->assertForbidden();
});

it('refuses to bring on a player who is already on the pitch', function () {
    $user = User::factory()->create();

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $match = LiveMatch::query()->findOrFail($matchId);
    $onPitch = collect($match->players)->firstWhere('pid', '!=', null);

    $this->actingAs($user)
        ->postJson(route('play.sub', $matchId), [
            'out_slot' => $onPitch['slot'] === 1 ? 2 : 1,
            'player_id' => $onPitch['pid'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('player_id');

    expect(LiveMatch::query()->findOrFail($matchId)->subs_remaining)->toBe(5);
});

it('records who came off when a substitution is made', function () {
    $user = User::factory()->create();

    $props = $this->actingAs($user)->get(route('play.show'))->viewData('page')['props'];
    $matchId = $props['matchId'];
    $incoming = $props['bench'][0]['id'];

    $before = LiveMatch::query()->findOrFail($matchId);
    $starter = collect($before->players)->firstWhere(fn (array $p): bool => $p['s'] === 0 && $p['slot'] === 1);

    $this->actingAs($user)
        ->postJson(route('play.sub', $matchId), ['out_slot' => 1, 'player_id' => $incoming])
        ->assertOk()
        ->assertJson(['subsRemaining' => 4]);

    $after = LiveMatch::query()->findOrFail($matchId);
    $sub = collect($after->moments)->firstWhere('kind', 'sub');

    // The player who came off used to be overwritten and lost entirely.
    expect($sub)->not->toBeNull()
        ->and($sub['text'])->toContain($starter['name'])
        ->and(collect($after->players)->firstWhere('slot', 1)['pid'])->toBe($incoming);
});

it('rejects a mentality that is not one of the three', function () {
    $user = User::factory()->create();
    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    // This used to return 200 echoing the requested value while changing nothing.
    $this->actingAs($user)
        ->postJson(route('play.mentality', $matchId), ['mentality' => 'gegenpress'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('mentality');

    expect(LiveMatch::query()->findOrFail($matchId)->pitch_state['homeMentality'])->toBe('balanced');
});

it('does not let someone else substitute into your match', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    $this->actingAs($other)
        ->postJson(route('play.sub', $matchId), ['out_slot' => 1, 'player_id' => 1])
        ->assertForbidden();

    $this->actingAs($other)
        ->postJson(route('play.mentality', $matchId), ['mentality' => 'attacking'])
        ->assertForbidden();
});
