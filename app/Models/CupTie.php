<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $season_id
 * @property int $round
 * @property int $slot
 * @property string $home
 * @property string|null $away
 * @property int|null $home_goals
 * @property int|null $away_goals
 * @property string|null $winner
 * @property bool $played
 * @property int $seed
 */
#[Fillable(['season_id', 'round', 'slot', 'home', 'away', 'home_goals', 'away_goals', 'winner', 'played', 'seed'])]
class CupTie extends Model
{
    /** The entrant string standing for the user's own club. */
    public const string USER = 'user';

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function isBye(): bool
    {
        return $this->away === null;
    }

    public function involvesUser(): bool
    {
        return $this->home === self::USER || $this->away === self::USER;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'played' => 'boolean',
            'round' => 'integer',
            'slot' => 'integer',
            'seed' => 'integer',
        ];
    }
}
