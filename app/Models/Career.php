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
 * @property Carbon|null $last_played_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'type', 'status', 'last_played_at'])]
class Career extends Model
{
    /** @use HasFactory<CareerFactory> */
    use HasFactory;

    /** Played against NPC clubs alone. */
    public const string SOLO = 'solo';

    /** Shared with other managers, each running their own club. */
    public const string LEAGUE = 'league';

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
