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
 * @property int $number
 * @property int $division
 * @property Carbon $starts_on
 * @property Carbon $current_date
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'number', 'division', 'starts_on', 'current_date', 'completed_at'])]
class Season extends Model
{
    /** The campaign always kicks off on this date; matchdays fall one week apart. */
    public const string STARTS_ON = '2025-08-09';

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
            'number' => 'integer',
            'division' => 'integer',
            'starts_on' => 'date',
            'current_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class)->orderBy('matchday')->orderBy('id');
    }

    /**
     * @return HasMany<CupTie, $this>
     */
    public function cupTies(): HasMany
    {
        return $this->hasMany(CupTie::class)->orderBy('round')->orderBy('slot');
    }

    /**
     * @return HasMany<Friendly, $this>
     */
    public function friendlies(): HasMany
    {
        return $this->hasMany(Friendly::class)->orderBy('slot');
    }
}
