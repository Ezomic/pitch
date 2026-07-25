<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $season_id
 * @property int $slot
 * @property int $opponent_team_id
 * @property bool $home
 * @property int|null $user_goals
 * @property int|null $opponent_goals
 * @property bool $played
 * @property int $seed
 */
#[Fillable(['season_id', 'slot', 'opponent_team_id', 'home', 'user_goals', 'opponent_goals', 'played', 'seed'])]
class Friendly extends Model
{
    protected $table = 'friendlies';

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function opponent(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'opponent_team_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'home' => 'boolean',
            'played' => 'boolean',
            'slot' => 'integer',
            'seed' => 'integer',
        ];
    }
}
