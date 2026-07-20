<?php

declare(strict_types=1);

use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

it('redirects the challenge to login when there is no pending user', function () {
    $this->get(route('two-factor.login'))->assertRedirect(route('login'));
});

it('renders the challenge for a pending two-factor user', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
        'two_factor_confirmed_at' => now(),
    ]);

    Mail::fake();
    $this->post(route('login.code.send'), ['email' => $user->email]);
    $code = null;
    Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->post(route('login.code.verify'), ['email' => $user->email, 'code' => $code]);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/TwoFactorChallenge'));
});
