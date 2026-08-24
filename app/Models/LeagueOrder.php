<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one manager told their club to do on one matchday.
 *
 * Kept after the round is played rather than folded into the result: a league
 * match is only reproducible if the orders that shaped it are still on record
 * alongside the fixture's seed.
 *
 * @property int $id
 * @property int $league_round_id
 * @property int $career_membership_id
 * @property string $formation
 * @property string $mentality
 * @property bool $ready
 * @property bool $auto
 */
#[Fillable(['league_round_id', 'career_membership_id', 'formation', 'mentality', 'ready', 'auto'])]
class LeagueOrder extends Model
{
    /**
     * @return BelongsTo<LeagueRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(LeagueRound::class, 'league_round_id');
    }

    /**
     * @return BelongsTo<CareerMembership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(CareerMembership::class, 'career_membership_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ready' => 'boolean',
            'auto' => 'boolean',
        ];
    }
}
