<?php

declare(strict_types=1);

namespace App\Models;

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Roster;
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

    /**
     * The squad's attributes keyed by formation slot, with empty slots filled by
     * an average placeholder so the engine always has a full eleven.
     *
     * @return array<int, Attributes>
     */
    public function attributesBySlot(): array
    {
        $bySlot = [];

        foreach ($this->assignments()->with('player')->get() as $assignment) {
            $bySlot[$assignment->slot] = $assignment->player->attributes();
        }

        foreach (Roster::slots() as $slot) {
            $bySlot[$slot] ??= new Attributes(10, 10, 10, 10, 10, 10);
        }

        return $bySlot;
    }
}
