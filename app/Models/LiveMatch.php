<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A positional match the user is playing out live: it stores the exact engine
 * state (a serialised PitchState plus the Rng position) so the tick loop can be
 * paused between requests and resumed with byte-identical results, and the
 * player metadata and feed the 2D replay needs.
 *
 * @property int $id
 * @property int $user_id
 * @property int $seed
 * @property int $current_tick
 * @property int $total_ticks
 * @property array<string, mixed> $pitch_state
 * @property int $rng_state
 * @property int $home_goals
 * @property int $away_goals
 * @property string $home_name
 * @property string $away_name
 * @property array<int, array<string, mixed>> $players
 * @property array<int, array<string, mixed>> $moments
 * @property int $subs_remaining
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'seed', 'current_tick', 'total_ticks', 'pitch_state', 'rng_state',
    'home_goals', 'away_goals', 'home_name', 'away_name', 'players', 'moments',
    'subs_remaining', 'status',
])]
class LiveMatch extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pitch_state' => 'array',
            'players' => 'array',
            'moments' => 'array',
            'seed' => 'integer',
            'current_tick' => 'integer',
            'total_ticks' => 'integer',
            'rng_state' => 'integer',
            'home_goals' => 'integer',
            'away_goals' => 'integer',
            'subs_remaining' => 'integer',
        ];
    }
}
