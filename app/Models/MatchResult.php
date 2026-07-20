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
 * @property int $simulation_run_id
 * @property string $arm
 * @property int $seed
 * @property int $home_score
 * @property int $away_score
 * @property int $shots
 * @property int $chances
 * @property int $passes_completed
 * @property int $progressive_passes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'simulation_run_id',
    'arm',
    'seed',
    'home_score',
    'away_score',
    'shots',
    'chances',
    'passes_completed',
    'progressive_passes',
])]
class MatchResult extends Model
{
    /**
     * @return BelongsTo<SimulationRun, $this>
     */
    public function simulationRun(): BelongsTo
    {
        return $this->belongsTo(SimulationRun::class);
    }

    /**
     * @return HasMany<MatchEvent, $this>
     */
    public function matchEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class);
    }
}
