<?php

declare(strict_types=1);

use App\Actions\Squad\AssignSetPieceTaker;
use App\Actions\Squad\EnsureSquad;
use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use App\Sim\Domain\Attributes;
use App\Sim\Domain\Position;
use App\Sim\Engine\Defense;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Engine\Roster;
use App\Sim\Engine\SetPieces;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\TeamSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function sideWithSetPiece(int $setPiece): TeamSetup
{
    $bySlot = [];
    foreach (Roster::slots() as $slot) {
        $bySlot[$slot] = new Attributes(60, 60, 60, 60, 60, 60);
    }

    return new TeamSetup($bySlot, Formation::balanced(), Mentality::Balanced, 55, $setPiece);
}

it('turns more set pieces into goals as the taker improves', function () {
    $resolver = new SetPieces;
    $defence = Defense::fromAttributes(
        collect(Roster::slots())->mapWithKeys(fn ($s) => [$s => new Attributes(55, 55, 55, 55, 55, 55)])->all(),
        Formation::balanced(),
        1.0,
        55,
    );

    $weak = 0;
    $strong = 0;
    for ($seed = 1; $seed <= 100; $seed++) {
        $weak += $resolver->resolve(25, $defence, $seed, 8)['goals'];
        $strong += $resolver->resolve(90, $defence, $seed, 8)['goals'];
    }

    expect($strong)->toBeGreaterThan($weak);
});

it('is deterministic for a fixed set-piece rating', function () {
    $resolver = new SetPieces;
    $defence = Defense::none();

    $a = $resolver->resolve(70, $defence, 42, 6);
    $b = $resolver->resolve(70, $defence, 42, 6);

    expect($a)->toBe($b);
});

it('scores more overall with a stronger set-piece taker in the squad profile', function () {
    $evaluator = new SquadEvaluator;
    $opponent = TeamSetup::baseline();

    $weak = $evaluator->evaluate(sideWithSetPiece(25), $opponent, 120);
    $strong = $evaluator->evaluate(sideWithSetPiece(90), $opponent, 120);

    expect($strong->goalsPer90)->toBeGreaterThan($weak->goalsPer90);
});

it('assigns a set-piece taker and derives a rating from delivery and finishing', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220]);
    $taker = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'passing' => 80, 'finishing' => 70, 'fitness' => 100, 'form' => 0]);

    app(AssignSetPieceTaker::class)->handle($squad, $taker);

    expect($squad->refresh()->set_piece_taker_id)->toBe($taker->id)
        ->and($squad->setPieceRating())->toBe(75);
});

it('refuses to nominate a goalkeeper as the set-piece taker', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220]);
    $keeper = Player::factory()->create(['user_id' => $user->id, 'is_youth' => false, 'position' => Position::Goalkeeper]);

    expect(fn () => app(AssignSetPieceTaker::class)->handle($squad, $keeper))
        ->toThrow(ValidationException::class);
});

it('falls back to a makeshift set-piece level with no taker', function () {
    $user = User::factory()->create();
    $squad = Squad::create(['user_id' => $user->id, 'name' => 'Test', 'budget' => 220]);

    expect($squad->setPieceRating())->toBe(Squad::DEFAULT_SET_PIECES);
});

it('exposes takers and the current taker on the squad page', function () {
    $user = User::factory()->create();
    Player::factory()->count(14)->create(['is_youth' => false]);
    app(EnsureSquad::class)->handle($user);

    $this->actingAs($user)
        ->get(route('squad.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Squad')
            ->has('takers')
            ->has('takers.0.rating')
            ->has('squad.setPieceTakerId'),
        );
});
