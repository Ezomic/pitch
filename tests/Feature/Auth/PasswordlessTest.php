<?php

declare(strict_types=1);

use App\Actions\Auth\SendLoginCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('has no password column on the users table', function () {
    expect(Schema::hasColumn('users', 'password'))->toBeFalse();
});

it('stores login code columns on the users table', function () {
    expect(Schema::hasColumn('users', 'login_code_hash'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'login_code_expires_at'))->toBeTrue();
});

it('does not register password reset routes', function () {
    expect(Route::has('password.request'))->toBeFalse()
        ->and(Route::has('password.email'))->toBeFalse();
});

it('uses the fixed dev login code when set in the local environment', function () {
    Mail::fake();
    app()->detectEnvironment(fn () => 'local');
    config(['auth.dev_login_code' => '424242']);

    $user = User::factory()->create();
    app(SendLoginCode::class)->handle($user);

    expect(Hash::check('424242', $user->refresh()->login_code_hash))->toBeTrue();
});

it('ignores the fixed dev login code outside the local environment', function () {
    Mail::fake();
    app()->detectEnvironment(fn () => 'production');
    config(['auth.dev_login_code' => '424242']);

    $user = User::factory()->create();
    app(SendLoginCode::class)->handle($user);

    expect(Hash::check('424242', $user->refresh()->login_code_hash))->toBeFalse();
});
