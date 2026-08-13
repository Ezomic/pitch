<?php

declare(strict_types=1);

use App\Actions\News\RecordNews;
use App\Actions\Season\EnsureSeason;
use App\Actions\Squad\EnsureSquad;
use App\Models\Career;
use App\Models\LiveMatch;
use App\Models\News;
use App\Models\Player;
use App\Models\Season;
use App\Models\Squad;
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

function switchTo(User $user, Career $career): void
{
    $user->forceFill(['current_career_id' => $career->id])->save();
    $user->refresh();
}

it('gives a manager with no career one, and remembers it', function () {
    $user = User::factory()->create();

    expect($user->current_career_id)->toBeNull();

    $career = $user->currentCareer();

    expect($career)->toBeInstanceOf(Career::class)
        ->and($user->fresh()->current_career_id)->toBe($career->id)
        // Asking again does not keep minting saves.
        ->and($user->currentCareer()->id)->toBe($career->id)
        ->and($user->careers()->count())->toBe(1);
});

it('stamps the save onto everything a career creates', function () {
    $user = User::factory()->create();
    $career = $user->currentCareer();

    $squad = app(EnsureSquad::class)->handle($user);
    $season = app(EnsureSeason::class)->handle($user);

    expect($squad->career_id)->toBe($career->id)
        ->and($season->career_id)->toBe($career->id);
});

it('keeps two careers from seeing each other', function () {
    $user = User::factory()->create();

    $first = $user->currentCareer();
    app(EnsureSquad::class)->handle($user);
    app(EnsureSeason::class)->handle($user);

    $second = $user->careers()->create([
        'name' => 'Second save', 'type' => Career::SOLO, 'status' => 'active', 'last_played_at' => now(),
    ]);
    switchTo($user, $second);

    // The second save starts empty rather than inheriting the first one's club.
    expect(Squad::query()->where('career_id', $second->id)->count())->toBe(0)
        ->and($user->squad()->first())->toBeNull()
        ->and($user->season()->first())->toBeNull();

    // Playing it out builds its own, leaving the first untouched.
    $secondSquad = app(EnsureSquad::class)->handle($user);
    $secondSeason = app(EnsureSeason::class)->handle($user);

    expect($secondSquad->career_id)->toBe($second->id)
        ->and($secondSeason->career_id)->toBe($second->id)
        ->and(Squad::query()->where('career_id', $first->id)->count())->toBe(1)
        ->and(Season::query()->where('career_id', $first->id)->count())->toBe(1);

    switchTo($user, $first);
    expect($user->squad()->first()->career_id)->toBe($first->id);
});

it('shows a save only its own news', function () {
    $user = User::factory()->create();
    $first = $user->currentCareer();

    app(RecordNews::class)->handle($user->id, News::BOARD, 'From the first save', 'body');

    $second = $user->careers()->create([
        'name' => 'Second', 'type' => Career::SOLO, 'status' => 'active', 'last_played_at' => now(),
    ]);
    switchTo($user, $second);

    app(RecordNews::class)->handle($user->id, News::BOARD, 'From the second save', 'body');

    $this->actingAs($user)->get(route('news.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('items', 1)
            ->where('items.0.title', 'From the second save'));

    switchTo($user, $first);

    $this->actingAs($user)->get(route('news.index'))
        ->assertInertia(fn ($page) => $page->has('items', 1)
            ->where('items.0.title', 'From the first save'));
});

it('does not resume a match belonging to another save', function () {
    $user = User::factory()->create();
    $first = $user->currentCareer();

    $matchId = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    expect(LiveMatch::query()->findOrFail($matchId)->career_id)->toBe($first->id);

    $second = $user->careers()->create([
        'name' => 'Second', 'type' => Career::SOLO, 'status' => 'active', 'last_played_at' => now(),
    ]);
    switchTo($user, $second);

    $other = $this->actingAs($user)->get(route('play.show'))
        ->viewData('page')['props']['matchId'];

    // The first save's match is still live, but this one gets its own.
    expect($other)->not->toBe($matchId)
        ->and(LiveMatch::query()->findOrFail($matchId)->status)->toBe(LiveMatch::LIVE)
        ->and(LiveMatch::query()->findOrFail($other)->career_id)->toBe($second->id);
});

it('leaves the seeded world pool shared until a squad claims from it', function () {
    $user = User::factory()->create();
    $career = $user->currentCareer();

    // Pool players carry no save of their own, so a new career can still field a
    // side. Owned state is per-career; the world they play in is not, yet.
    expect(Player::query()->whereNull('career_id')->count())->toBeGreaterThan(0)
        ->and(Player::query()->selectableFor($career->id)->count())->toBeGreaterThan(10);
});

it('lets a brand new save field a side', function () {
    $user = User::factory()->create();
    $user->currentCareer();
    app(EnsureSquad::class)->handle($user);

    $second = $user->careers()->create([
        'name' => 'Second', 'type' => Career::SOLO, 'status' => 'active', 'last_played_at' => now(),
    ]);
    switchTo($user, $second);

    // The first save claimed eleven players. What is left of the world pool has
    // to be enough for a second save to put a team out, or starting one gives a
    // manager an empty club.
    $squad = app(EnsureSquad::class)->handle($user);

    expect($squad->career_id)->toBe($second->id)
        ->and($squad->assignments()->count())->toBeGreaterThan(0);
});
