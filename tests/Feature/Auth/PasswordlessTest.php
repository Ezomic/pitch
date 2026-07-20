<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
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
