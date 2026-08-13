<?php

declare(strict_types=1);

namespace App\Actions\Scout;

use App\Enums\ScoutStatus;
use App\Models\User;

/**
 * Keep a small market of hireable scouts in front of the user, topping it back
 * up as they hire from it.
 */
class EnsureScouts
{
    private const int MARKET_SIZE = 3;

    public function handle(User $user): void
    {
        $available = $user->scouts()->where('status', ScoutStatus::Available)->count();

        for ($i = $available; $i < self::MARKET_SIZE; $i++) {
            $user->scouts()->create([
                // A constrained relation sets only the foreign key on create, so
                // the save has to be stamped explicitly or the scout belongs to none.
                'career_id' => $user->currentCareerId(),
                'name' => fake()->name(),
                'rating' => random_int(1, 5),
                'status' => ScoutStatus::Available,
            ]);
        }
    }
}
