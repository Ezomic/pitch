<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $fixture_id
 * @property int $seed
 * @property int $home_goals
 * @property int $away_goals
 * @property array<int, array<string, mixed>> $moments
 * @property array<int, int>|null $lineup
 * @property array<int, int>|null $bench
 * @property int $subs_remaining
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'fixture_id', 'seed', 'home_goals', 'away_goals', 'moments', 'lineup', 'bench', 'subs_remaining', 'status'])]
class MatchSession extends Model
{
    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moments' => 'array',
            'lineup' => 'array',
            'bench' => 'array',
            'home_goals' => 'integer',
            'away_goals' => 'integer',
            'subs_remaining' => 'integer',
            'seed' => 'integer',
        ];
    }
}
