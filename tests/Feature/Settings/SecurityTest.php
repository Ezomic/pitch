<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('displays the security page without password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManagePasskeys', true)
            ->where('passkeys', [])
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false),
        );
});

it('has no password update route', function () {
    expect(Route::has('user-password.update'))->toBeFalse();
});

it('enables two-factor authentication without a password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('two-factor.enable'));

    expect($user->refresh()->two_factor_secret)->not->toBeNull();
});

it('disables two-factor authentication', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)->delete(route('two-factor.disable'));

    expect($user->refresh()->two_factor_secret)->toBeNull();
});
