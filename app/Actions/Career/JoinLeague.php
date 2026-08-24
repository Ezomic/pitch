<?php

declare(strict_types=1);

namespace App\Actions\Career;

use App\Models\CareerInvitation;
use App\Models\CareerMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Take an invitation and sit down in the league.
 *
 * A manager arrives without a club: every side starts as AI and one is claimed
 * afterwards, so joining and picking who you run are separate decisions.
 */
class JoinLeague
{
    /** The seat taken, or null when the invitation cannot be used. */
    public function handle(CareerInvitation $invitation, User $user): ?CareerMembership
    {
        if (! $invitation->isOpen()) {
            return null;
        }

        return DB::transaction(function () use ($invitation, $user): CareerMembership {
            $career = $invitation->career;

            // Already in this league: the invitation is left open for someone else
            // rather than burned on a manager who is here.
            $existing = $career->seatFor($user);

            if ($existing !== null) {
                return $existing;
            }

            $invitation->claim($user);

            return $career->memberships()->create([
                'user_id' => $user->id,
                'role' => CareerMembership::MEMBER,
            ]);
        });
    }
}
