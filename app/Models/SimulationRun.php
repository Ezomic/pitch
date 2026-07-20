<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $seed
 * @property int $matches
 * @property int $low_vision
 * @property int $high_vision
 * @property bool $separated
 * @property array<string, mixed> $report
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['seed', 'matches', 'low_vision', 'high_vision', 'separated', 'report'])]
class SimulationRun extends Model
{
    /**
     * @return HasMany<MatchResult, $this>
     */
    public function matchResults(): HasMany
    {
        return $this->hasMany(MatchResult::class);
    }

    /**
     * @return HasMany<MatchEvent, $this>
     */
    public function matchEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'separated' => 'boolean',
            'report' => 'array',
        ];
    }
}
