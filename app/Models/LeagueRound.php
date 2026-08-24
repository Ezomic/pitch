<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * One matchday of a league, and the gate in front of it.
 *
 * A round stays open while managers pick their side. It plays when everyone is
 * ready, or when the deadline runs out and the ones who never showed up are
 * played by the AI, so a league cannot be stalled by a manager who stops
 * logging in.
 *
 * @property int $id
 * @property int $career_id
 * @property int $season_id
 * @property int $matchday
 * @property string $status
 * @property Carbon|null $deadline_at
 * @property Carbon|null $resolved_at
 */
#[Fillable(['career_id', 'season_id', 'matchday', 'status', 'deadline_at', 'resolved_at'])]
class LeagueRound extends Model
{
    public const string OPEN = 'open';

    public const string RESOLVED = 'resolved';

    /**
     * @return BelongsTo<Career, $this>
     */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return HasMany<LeagueOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(LeagueOrder::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function deadlinePassed(): bool
    {
        return $this->deadline_at !== null && $this->deadline_at->isPast();
    }

    public function orderFor(CareerMembership $seat): ?LeagueOrder
    {
        return $this->orders()->where('career_membership_id', $seat->id)->first();
    }

    public function markResolved(): void
    {
        $this->forceFill(['status' => self::RESOLVED, 'resolved_at' => Date::now()])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'matchday' => 'integer',
            'deadline_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
