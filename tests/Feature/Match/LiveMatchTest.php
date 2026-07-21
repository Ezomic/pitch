<?php

declare(strict_types=1);

use App\Actions\Season\AdvanceWeek;
use App\Actions\Season\EnsureSeason;
use App\Actions\Squad\EnsureSquad;
use App\Models\Fixture;
use App\Models\MatchSession;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Sim\Domain\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Player::factory()->count(8)->create(['position' => Position::Defender]);
    Player::factory()->count(8)->create(['position' => Position::Midfielder]);
    Player::factory()->count(8)->create(['position' => Position::Forward]);
    Team::factory()->count(7)->create(['is_youth' => false]);
});

function readyFixture(User $user): Fixture
{
    app(EnsureSquad::class)->handle($user);
    $season = app(EnsureSeason::class)->handle($user);
    app(AdvanceWeek::class)->handle($season); // resolves rivals, leaves the user fixture due

    return $season->fixtures()
        ->where('played', false)
        ->where(fn ($q) => $q->whereNull('home_team_id')->orWhereNull('away_team_id'))
        ->firstOrFail();
}

it('offers the live match and blocks advancing while it is due', function () {
    $user = User::factory()->create();
    readyFixture($user);
    $dateAfterAdvance = $user->season->current_date->toDateString();

    $this->actingAs($user)
        ->get(route('season.show'))
        ->assertInertia(fn (Assert $page) => $page->has('liveFixture.url')->has('liveFixture.opponentName'));

    // Advancing again does nothing until the live match is played.
    $this->actingAs($user)->post(route('season.advance'));
    expect($user->season->refresh()->current_date->toDateString())->toBe($dateAfterAdvance);
});

it('renders the live match and opens a session', function () {
    $user = User::factory()->create();
    $fixture = readyFixture($user);

    $this->actingAs($user)
        ->get(route('match.live.show', $fixture))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('LiveMatch')
            ->has('opponentName')
            ->has('moments')
            ->has('lineup.0.fitness')
            ->has('lineup.0.form')
            ->has('finishUrl'),
        );

    expect(MatchSession::where('user_id', $user->id)->where('fixture_id', $fixture->id)->exists())->toBeTrue();
});

it('records the played-out score onto the fixture at full time', function () {
    $user = User::factory()->create();
    $fixture = readyFixture($user);

    $this->actingAs($user)->get(route('match.live.show', $fixture));
    $session = MatchSession::where('fixture_id', $fixture->id)->firstOrFail();
    $xi = array_map('intval', array_values($session->lineup));

    $this->actingAs($user)
        ->post(route('match.live.finish', $fixture))
        ->assertRedirect(route('season.show'));

    $fixture->refresh();
    [$expectedHome, $expectedAway] = $fixture->userIsHome()
        ? [$session->home_goals, $session->away_goals]
        : [$session->away_goals, $session->home_goals];

    expect($fixture->played)->toBeTrue()
        ->and($fixture->home_goals)->toBe($expectedHome)
        ->and($fixture->away_goals)->toBe($expectedAway)
        ->and(MatchSession::where('fixture_id', $fixture->id)->exists())->toBeFalse()
        // The XI leaves the pitch tired: full-time drains their fitness.
        ->and(Player::find($xi[0])->fitness)->toBe(100 - Player::MATCH_DRAIN);
});

it('names a bench and substitutes from it, re-simulating the rest of the match', function () {
    $user = User::factory()->create();
    $fixture = readyFixture($user);
    $this->actingAs($user)->get(route('match.live.show', $fixture));
    $session = MatchSession::where('fixture_id', $fixture->id)->firstOrFail();

    $xi = array_values($session->lineup);
    $benchPlayer = Player::query()->selectableFor($user->id)->whereNotIn('id', $xi)->firstOrFail();

    $this->actingAs($user)
        ->post(route('match.live.bench', $fixture), ['players' => [$benchPlayer->id]])
        ->assertRedirect(route('match.live.show', $fixture));
    expect($session->refresh()->bench)->toContain($benchPlayer->id);

    $slot = (int) array_key_first($session->lineup);
    $before = $session->moments;

    $this->actingAs($user)
        ->post(route('match.live.sub', $fixture), ['minute' => 40, 'slot' => $slot, 'in' => $benchPlayer->id])
        ->assertRedirect(route('match.live.show', $fixture));

    $session->refresh();
    expect($session->subs_remaining)->toBe(2)
        ->and($session->lineup[$slot])->toBe($benchPlayer->id)
        ->and($session->bench)->not->toContain($benchPlayer->id)
        // Moments before the sub minute are locked; the whole feed is a fresh tail after it.
        ->and($session->moments)->not->toBe($before);
});

it('rejects a bench player who is already in the XI', function () {
    $user = User::factory()->create();
    $fixture = readyFixture($user);
    $this->actingAs($user)->get(route('match.live.show', $fixture));
    $session = MatchSession::where('fixture_id', $fixture->id)->firstOrFail();
    $onPitch = (int) array_values($session->lineup)[0];

    $this->actingAs($user)
        ->from(route('match.live.show', $fixture))
        ->post(route('match.live.bench', $fixture), ['players' => [$onPitch]])
        ->assertSessionHasErrors('bench');
});

it('caps substitutions at three', function () {
    $user = User::factory()->create();
    $fixture = readyFixture($user);
    $this->actingAs($user)->get(route('match.live.show', $fixture));
    $session = MatchSession::where('fixture_id', $fixture->id)->firstOrFail();

    $xi = array_values($session->lineup);
    $bench = Player::query()->selectableFor($user->id)->whereNotIn('id', $xi)->take(4)->pluck('id')->all();
    $this->actingAs($user)->post(route('match.live.bench', $fixture), ['players' => $bench]);

    $slots = array_keys($session->refresh()->lineup);
    foreach (array_slice($bench, 0, 3) as $i => $in) {
        $this->actingAs($user)
            ->post(route('match.live.sub', $fixture), ['minute' => 30, 'slot' => $slots[$i], 'in' => $in])
            ->assertRedirect();
    }

    $this->actingAs($user)
        ->from(route('match.live.show', $fixture))
        ->post(route('match.live.sub', $fixture), ['minute' => 30, 'slot' => $slots[3], 'in' => $bench[3]])
        ->assertSessionHasErrors('sub');
    expect($session->refresh()->subs_remaining)->toBe(0);
});

it('will not open a live match for a played or rival-only fixture', function () {
    $user = User::factory()->create();
    $season = app(EnsureSeason::class)->handle($user);
    $rivalFixture = $season->fixtures()
        ->whereNotNull('home_team_id')->whereNotNull('away_team_id')->firstOrFail();

    $this->actingAs($user)->get(route('match.live.show', $rivalFixture))->assertNotFound();
});
