<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;

/**
 * A game row belongs to a save, not to a manager.
 *
 * Rather than making every caller remember to stamp it, a row created against a
 * user takes that manager's active career unless one was named. Anything with
 * no user at all is left unclaimed: the seeded world pool is shared until a
 * squad signs from it.
 */
trait BelongsToCareer
{
    protected static function bootBelongsToCareer(): void
    {
        static::creating(function (self $model): void {
            if ($model->career_id !== null || $model->user_id === null) {
                return;
            }

            $model->career_id = User::query()->find($model->user_id)?->currentCareerId();
        });
    }
}
