<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the registration screen', function () {
    $this->get(route('register'))->assertOk();
});

it('creates a passwordless account from name and email', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Test User');

    $this->assertAuthenticatedAs($user);
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'taken@example.com',
    ])->assertSessionHasErrors('email');
});
