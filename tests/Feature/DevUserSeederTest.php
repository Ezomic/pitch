<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a playable dev account outside production', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::where('email', config('auth.dev_login_email'))->first();

    expect($user)->not->toBeNull()
        ->and($user->squad()->exists())->toBeTrue()
        ->and($user->season()->exists())->toBeTrue();
});

it('does not seed the dev account in production', function () {
    $this->app['env'] = 'production';

    // Invoke the seeder directly: $this->seed() would trip the production confirm prompt.
    $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->run();

    expect(User::where('email', config('auth.dev_login_email'))->exists())->toBeFalse();
});

it('is idempotent across repeated seeds', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', config('auth.dev_login_email'))->count())->toBe(1);
});
