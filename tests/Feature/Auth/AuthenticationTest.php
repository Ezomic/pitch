<?php

declare(strict_types=1);

use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

function sendAndCaptureCode(User $user): string
{
    Mail::fake();

    test()->post(route('login.code.send'), ['email' => $user->email]);

    $captured = null;
    Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$captured) {
        $captured = $mail->code;

        return true;
    });

    return $captured;
}

it('renders the login screen', function () {
    $this->get(route('login'))->assertOk();
});

it('authenticates a user with an emailed login code', function () {
    $user = User::factory()->create();

    $code = sendAndCaptureCode($user);

    $this->post(route('login.code.verify'), [
        'email' => $user->email,
        'code' => $code,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid login code', function () {
    $user = User::factory()->create();
    sendAndCaptureCode($user);

    $this->post(route('login.code.verify'), [
        'email' => $user->email,
        'code' => '000000',
    ])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('rejects an expired login code', function () {
    $user = User::factory()->create();
    $code = sendAndCaptureCode($user);

    $user->forceFill(['login_code_expires_at' => now()->subMinute()])->save();

    $this->post(route('login.code.verify'), [
        'email' => $user->email,
        'code' => $code,
    ])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('redirects a two-factor user to the challenge instead of logging in', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
        'two_factor_confirmed_at' => now(),
    ]);

    $code = sendAndCaptureCode($user);

    $response = $this->post(route('login.code.verify'), [
        'email' => $user->email,
        'code' => $code,
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

it('completes login after a valid two-factor code', function () {
    $secret = app(Google2FA::class)->generateSecretKey();
    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_confirmed_at' => now(),
    ]);

    $code = sendAndCaptureCode($user);
    $this->post(route('login.code.verify'), ['email' => $user->email, 'code' => $code]);

    $this->post(route('two-factor.login.store'), [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('completes login with a recovery code', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $code = sendAndCaptureCode($user);
    $this->post(route('login.code.verify'), ['email' => $user->email, 'code' => $code]);

    $this->post(route('two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('logs the user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

it('rate limits login code requests', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $this->post(route('login.code.send'), ['email' => $user->email])
        ->assertTooManyRequests();
});
