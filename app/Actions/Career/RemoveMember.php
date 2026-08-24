<?php

declare(strict_types=1);

namespace App\Actions\Career;

use App\Models\CareerMembership;

/**
 * Take a manager out of a league. Their club goes back to the AI, which is
 * what keeps a league playable after somebody leaves.
 *
 * The owner cannot be removed: a league without one has nobody to run it.
 */
class RemoveMember
{
    public function handle(CareerMembership $seat): bool
    {
        if ($seat->isOwner()) {
            return false;
        }

        $seat->delete();

        return true;
    }
}
