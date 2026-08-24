<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CareerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One save: a whole game world of its own. A manager can keep several going at
 * once, so every piece of game state (squad, season, players, news) belongs to a
 * career rather than straight to the user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property string $status
 * @property int $round_hours
 * @property Carbon|null $last_played_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'type', 'status', 'round_hours', 'last_played_at'])]
class Career extends Model
{
    /** @use HasFactory<CareerFactory> */
    use HasFactory;

    /** Played against NPC clubs alone. */
    public const string SOLO = 'solo';

    /** Shared with other managers, each running their own club. */
    public const string LEAGUE = 'league';

    /** How long a league waits on a manager before playing the round without them. */
    public const int DEFAULT_ROUND_HOURS = 24;

    /**
     * Mirrors the column default so a freshly created career carries the cadence
     * without being read back: a round's deadline is set from it the moment the
     * league opens, which is often the same request.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['round_hours' => self::DEFAULT_ROUND_HOURS];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Squad, $this>
     */
    public function squads(): HasMany
    {
        return $this->hasMany(Squad::class);
    }

    /**
     * @return HasMany<Season, $this>
     */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    /**
     * The managers holding a seat in this league.
     *
     * @return HasMany<CareerMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CareerMembership::class);
    }

    /**
     * @return HasMany<CareerInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CareerInvitation::class);
    }

    /**
     * @return HasMany<LeagueRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(LeagueRound::class)->orderBy('matchday');
    }

    /** The seat this manager holds, if they hold one. */
    public function seatFor(User $user): ?CareerMembership
    {
        return $this->memberships()->where('user_id', $user->id)->first();
    }

    public function isLeague(): bool
    {
        return $this->type === self::LEAGUE;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_played_at' => 'datetime',
        ];
    }
}
