<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Thijssensoftware\IdClient\Http\Middleware\EnsureSsoSessionIsActive;

uses(RefreshDatabase::class);

function idIdentity(string $email, string $sub = 'idp-42'): SocialiteUser
{
    return (new SocialiteUser)->map(['id' => $sub, 'name' => 'Robbin', 'email' => $email]);
}

function fakeIdDriver(SocialiteUser $identity): void
{
    $driver = Mockery::mock(Provider::class);
    $driver->shouldReceive('user')->andReturn($identity);

    $factory = Mockery::mock(Socialite::class);
    $factory->shouldReceive('driver')->with('thijssensoftware')->andReturn($driver);

    app()->instance(Socialite::class, $factory);
}

function signedLogout(string $sub): TestResponse
{
    $body = json_encode(['sub' => $sub, 'issued_at' => Carbon::now()->getTimestamp(), 'nonce' => 'n'], JSON_THROW_ON_ERROR);

    return test()->call('POST', '/auth/sso/logout', server: [
        'HTTP_X_ID_SIGNATURE' => hash_hmac('sha256', $body, 'logout-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], content: $body);
}

beforeEach(function () {
    config()->set('services.thijssensoftware.base_url', 'https://id.example.test');
    config()->set('services.thijssensoftware.client_id', 'pitch-client');
    config()->set('id-client.logout_secret', 'logout-secret');
});

it('offers sign-in through ID on the login page', function () {
    $this->get(route('login'))->assertOk();
});

it('sends the visitor to ID to sign in', function () {
    $this->get(route('sso.redirect'))
        ->assertRedirectContains('https://id.example.test/oauth/authorize')
        ->assertRedirectContains('client_id=pitch-client');
});

it('links an existing pitch account to the ID user with the same email and signs it in', function () {
    $user = User::factory()->create(['email' => 'robbin@example.test']);
    fakeIdDriver(idIdentity('robbin@example.test'));

    $this->get(route('sso.callback'))->assertRedirect(route('dashboard', absolute: false));

    expect(Auth::id())->toBe($user->id)
        ->and($user->fresh()->idp_id)->toBe('idp-42');
});

it('provisions a pitch account for an ID user who has none', function () {
    fakeIdDriver(idIdentity('new@example.test', 'idp-7'));

    $this->get(route('sso.callback'))->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->where('idp_id', 'idp-7')->value('email'))->toBe('new@example.test')
        ->and(Auth::check())->toBeTrue();
});

it('ends a session started through ID when ID signs the user out', function () {
    $user = User::factory()->create(['idp_id' => 'idp-42']);
    $startedAt = [EnsureSsoSessionIsActive::AUTHENTICATED_AT => Carbon::now()->subMinute()->getTimestamp()];

    $this->actingAs($user)->withSession($startedAt)->get(route('dashboard'))->assertOk();

    signedLogout('idp-42')->assertOk();

    $this->actingAs($user->fresh())->withSession($startedAt)->get(route('dashboard'))->assertRedirect('/');

    expect(Auth::check())->toBeFalse();
});

it('leaves a session started with an emailed code alone when ID signs the user out', function () {
    $user = User::factory()->create(['idp_id' => 'idp-42']);

    $this->actingAs($user);
    signedLogout('idp-42')->assertOk();

    $this->get(route('dashboard'))->assertOk();
});

it('refuses a logout call that is not signed with the shared secret', function () {
    $user = User::factory()->create(['idp_id' => 'idp-42']);

    $this->postJson('/auth/sso/logout', ['sub' => 'idp-42'])->assertUnauthorized();

    expect($user->fresh()->sso_logged_out_at)->toBeNull();
});
