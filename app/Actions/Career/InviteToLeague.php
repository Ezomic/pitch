<?php

declare(strict_types=1);

namespace App\Actions\Career;

use App\Models\Career;
use App\Models\CareerInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/** Mint a single-use link that lets one manager into a league. */
class InviteToLeague
{
    /** Long enough that a link cannot be guessed, short enough to paste. */
    private const TOKEN_BYTES = 24;

    private const DEFAULT_DAYS = 7;

    public function handle(Career $career, User $owner, ?int $days = null): CareerInvitation
    {
        return $career->invitations()->create([
            'created_by' => $owner->id,
            'token' => Str::random(self::TOKEN_BYTES),
            'expires_at' => Date::now()->addDays($days ?? self::DEFAULT_DAYS),
        ]);
    }
}
