<?php

declare(strict_types=1);

use App\Actions\Season\EnsureSeason;
use App\Actions\Season\ProcessLoans;
use App\Actions\Youth\BuildYouthTeam;
use App\Actions\Youth\LoanOut;
use App\Actions\Youth\RecallLoan;
use App\Models\Player;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('loans a prospect out for a fixed spell', function () {
    $user = User::factory()->create();
    $prospect = Player::factory()->youth($user->id)->create();

    app(LoanOut::class)->handle($prospect);

    expect($prospect->refresh()->on_loan)->toBeTrue()
        ->and($prospect->loan_weeks_remaining)->toBe(Player::LOAN_WEEKS);
});

it('will not loan a senior or an already-loaned prospect', function () {
    $senior = Player::factory()->create(['is_youth' => false]);
    expect(fn () => app(LoanOut::class)->handle($senior))->toThrow(ValidationException::class);

    $onLoan = Player::factory()->youth()->create(['on_loan' => true, 'loan_weeks_remaining' => 5]);
    expect(fn () => app(LoanOut::class)->handle($onLoan))->toThrow(ValidationException::class);
});

it('develops a loaned prospect faster and returns it with a lifted ceiling', function () {
    $user = User::factory()->create();
    $prospect = Player::factory()->youth($user->id)->create([
        'on_loan' => true,
        'loan_weeks_remaining' => 1,
        'vision' => 20,
        'potential' => 80,
    ]);
    $potentialBefore = $prospect->potential;
    $season = Season::create(['user_id' => $user->id, 'number' => 1, 'starts_on' => Season::STARTS_ON, 'current_date' => Season::STARTS_ON]);

    app(ProcessLoans::class)->handle($season);

    $prospect->refresh();
    expect($prospect->on_loan)->toBeFalse()
        ->and($prospect->loan_weeks_remaining)->toBe(0)
        ->and($prospect->potential)->toBe(min(100, $potentialBefore + Player::LOAN_RETURN_POTENTIAL));
});

it('counts a loan down week by week', function () {
    $user = User::factory()->create();
    $prospect = Player::factory()->youth($user->id)->create(['on_loan' => true, 'loan_weeks_remaining' => 3]);
    $season = Season::create(['user_id' => $user->id, 'number' => 1, 'starts_on' => Season::STARTS_ON, 'current_date' => Season::STARTS_ON]);

    app(ProcessLoans::class)->handle($season);

    expect($prospect->refresh()->loan_weeks_remaining)->toBe(2)
        ->and($prospect->on_loan)->toBeTrue();
});

it('leaves a loaned prospect out of the youth XI', function () {
    $user = User::factory()->create();
    Player::factory()->youth($user->id)->count(11)->create();
    $loaned = Player::factory()->youth($user->id)->create(['on_loan' => true, 'loan_weeks_remaining' => 4, 'vision' => 99, 'passing' => 99, 'dribbling' => 99, 'finishing' => 99, 'tackling' => 99, 'pace' => 99]);

    $featured = app(BuildYouthTeam::class)->featured($user);

    expect($featured->pluck('id'))->not->toContain($loaned->id);
});

it('recalls a prospect early without the ceiling lift', function () {
    $user = User::factory()->create();
    $prospect = Player::factory()->youth($user->id)->create(['on_loan' => true, 'loan_weeks_remaining' => 6, 'potential' => 80]);

    app(RecallLoan::class)->handle($prospect);

    expect($prospect->refresh()->on_loan)->toBeFalse()
        ->and($prospect->loan_weeks_remaining)->toBe(0)
        ->and($prospect->potential)->toBe(80);
});

it('shows loan controls and status on the academy page', function () {
    $user = User::factory()->create();
    app(EnsureSeason::class)->handle($user);
    Player::factory()->youth($user->id)->create(['on_loan' => true, 'loan_weeks_remaining' => 7]);

    $this->actingAs($user)
        ->get(route('youth.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Youth')
            ->where('prospects.0.onLoan', true)
            ->where('prospects.0.loanWeeks', 7),
        );
});
