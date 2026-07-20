<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $simulation_run_id
 * @property int $match_result_id
 * @property int $minute
 * @property string $type
 * @property int $actor_id
 * @property int|null $target_id
 * @property bool $success
 * @property array<string, mixed>|null $decision
 * @property array<string, mixed>|null $roll
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'simulation_run_id',
    'match_result_id',
    'minute',
    'type',
    'actor_id',
    'target_id',
    'success',
    'decision',
    'roll',
])]
class MatchEvent extends Model
{
    /**
     * @return BelongsTo<SimulationRun, $this>
     */
    public function simulationRun(): BelongsTo
    {
        return $this->belongsTo(SimulationRun::class);
    }

    /**
     * @return BelongsTo<MatchResult, $this>
     */
    public function matchResult(): BelongsTo
    {
        return $this->belongsTo(MatchResult::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'decision' => 'array',
            'roll' => 'array',
        ];
    }
}
