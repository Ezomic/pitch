<?php

declare(strict_types=1);

use App\Actions\Match\FinishLiveMatch;
use App\Actions\News\GenerateTransferOffer;
use App\Actions\News\RecordNews;
use App\Actions\News\ResolveOffer;
use App\Actions\Squad\EnsureSquad;
use App\Models\MatchSession;
use App\Models\News;
use App\Models\Player;
use App\Models\Season;
use App\Models\Squad;
use App\Models\SquadPlayer;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lists the feed newest first and marks board items read on view', function () {
    $user = User::factory()->create();
    app(RecordNews::class)->handle($user->id, News::BOARD, 'Older', 'body');
    app(RecordNews::class)->handle($user->id, News::BOARD, 'Newer', 'body');

    $this->actingAs($user)
        ->get(route('news.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('News')
            ->has('items', 2)
            ->where('items.0.title', 'Newer'),
        );

    expect(News::query()->whereNull('read_at')->count())->toBe(0);
});

it('keeps open offers unread until they are resolved', function () {
    $user = User::factory()->create();
    app(RecordNews::class)->handle($user->id, News::OFFER, 'Offer', 'body', null, ['player_id' => 1, 'fee' => 10]);

    $this->actingAs($user)->get(route('news.index'))->assertOk();

    expect(News::query()->where('category', News::OFFER)->whereNull('read_at')->count())->toBe(1);
});

it('accepts a transfer offer, banking the fee and selling the player', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 100]);
    $player = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false]);
    $squad->assignments()->create(['player_id' => $player->id, 'slot' => 1]);
    $offer = app(RecordNews::class)->handle($user->id, News::OFFER, 'Offer', 'body', null, ['player_id' => $player->id, 'fee' => 45]);

    app(ResolveOffer::class)->handle($offer, $squad, accept: true);

    $player->refresh();
    expect($squad->refresh()->bank)->toBe(145)
        ->and($player->user_id)->toBeNull()
        ->and($player->is_free_agent)->toBeTrue()
        ->and($offer->refresh()->resolved_at)->not->toBeNull()
        ->and(SquadPlayer::query()->where('player_id', $player->id)->exists())->toBeFalse();
});

it('declines a transfer offer, leaving the player and bank untouched', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220, 'bank' => 100]);
    $player = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false]);
    $offer = app(RecordNews::class)->handle($user->id, News::OFFER, 'Offer', 'body', null, ['player_id' => $player->id, 'fee' => 45]);

    app(ResolveOffer::class)->handle($offer, $squad, accept: false);

    expect($squad->refresh()->bank)->toBe(100)
        ->and($player->refresh()->user_id)->toBe($user->id)
        ->and($offer->refresh()->resolved_at)->not->toBeNull();
});

it('generates at most one open offer at a time', function () {
    $user = User::factory()->create();
    Team::factory()->count(3)->create(['is_youth' => false]);
    Player::factory()->count(3)->create(['user_id' => $user->id, 'is_youth' => false, 'vision' => 80]);
    $season = Season::create(['user_id' => $user->id, 'number' => 1, 'starts_on' => Season::STARTS_ON, 'current_date' => Season::STARTS_ON]);

    // Force several weeks; only one offer should ever stand open.
    foreach (range(1, 12) as $week) {
        $season->forceFill(['current_date' => CarbonImmutable::parse(Season::STARTS_ON)->addWeeks($week)])->save();
        app(GenerateTransferOffer::class)->handle($season);
    }

    expect(News::query()->where('user_id', $user->id)->openOffers()->count())->toBeLessThanOrEqual(1);
});

it('files a result into the feed when a live match finishes', function () {
    $user = User::factory()->create();
    app(EnsureSquad::class)->handle($user);
    $season = Season::create(['user_id' => $user->id, 'number' => 1, 'starts_on' => Season::STARTS_ON, 'current_date' => Season::STARTS_ON]);
    $team = Team::factory()->create(['is_youth' => false]);
    $fixture = $season->fixtures()->create([
        'matchday' => 1, 'scheduled_on' => Season::STARTS_ON,
        'home_team_id' => null, 'away_team_id' => $team->id, 'seed' => 1, 'played' => false,
    ]);
    $session = MatchSession::create([
        'user_id' => $user->id, 'fixture_id' => $fixture->id, 'seed' => 1,
        'home_goals' => 2, 'away_goals' => 1, 'moments' => [], 'lineup' => [], 'scorers' => [],
    ]);

    app(FinishLiveMatch::class)->handle($session);

    $result = News::query()->where('user_id', $user->id)->where('category', News::RESULT)->first();
    expect($result)->not->toBeNull()
        ->and($result->title)->toContain('Won 2-1');
});
