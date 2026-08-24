<?php

declare(strict_types=1);

namespace App\Actions\Career;

use App\Models\CareerMembership;
use App\Models\Team;
use Illuminate\Database\QueryException;

/**
 * Take charge of one of the league's clubs.
 *
 * A club has one manager, which the database enforces with a unique index on
 * the pair rather than trusting a check-then-write: two managers claiming the
 * same side in the same moment must not both succeed.
 */
class ClaimClub
{
    public function handle(CareerMembership $seat, Team $team): bool
    {
        if ($team->is_youth) {
            return false;
        }

        try {
            $seat->forceFill(['team_id' => $team->id])->save();
        } catch (QueryException) {
            return false; // someone got there first
        }

        return true;
    }

    /** Give the club back to the AI. */
    public function release(CareerMembership $seat): void
    {
        $seat->forceFill(['team_id' => null])->save();
    }
}
