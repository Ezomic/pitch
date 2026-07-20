<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Mail\LoginCodeMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class SendLoginCode
{
    public function handle(User $user): void
    {
        $code = $this->fixedDevCode() ?? (string) random_int(100000, 999999);

        $user->forceFill([
            'login_code_hash' => Hash::make($code),
            'login_code_expires_at' => CarbonImmutable::now()->addMinutes(10),
        ])->save();

        Mail::to($user->email)->send(new LoginCodeMail($code));
    }

    private function fixedDevCode(): ?string
    {
        if (! app()->environment('local')) {
            return null;
        }

        $code = config('auth.dev_login_code');

        return is_string($code) && $code !== '' ? $code : null;
    }
}
