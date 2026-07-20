<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $budget
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'budget'])]
class Squad extends Model
{
    public const int DEFAULT_BUDGET = 220;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The player-to-slot assignments that make up this squad.
     *
     * @return HasMany<SquadPlayer, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SquadPlayer::class)->orderBy('slot');
    }
}
