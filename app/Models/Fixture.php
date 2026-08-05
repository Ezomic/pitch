<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $season_id
 * @property int $matchday
 * @property bool $youth
 * @property Carbon|null $scheduled_on
 * @property int|null $home_team_id
 * @property int|null $away_team_id
 * @property int|null $home_goals
 * @property int|null $away_goals
 * @property bool $played
 * @property int $seed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'season_id',
    'matchday',
    'youth',
    'scheduled_on',
    'home_team_id',
    'away_team_id',
    'home_goals',
    'away_goals',
    'played',
    'seed',
])]
class Fixture extends Model
{
    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function involvesUser(): bool
    {
        return $this->home_team_id === null || $this->away_team_id === null;
    }

    /**
     * The manager's own senior fixture that is due to be played: unplayed, and
     * scheduled on or before the season's current date. This is the match /play
     * puts you into, and the one the season waits on before rolling forward.
     */
    public static function dueFor(User $user): ?self
    {
        $season = Season::query()->where('user_id', $user->id)->whereNull('completed_at')->latest('id')->first();

        if (! $season instanceof Season) {
            return null;
        }

        return $season->fixtures()
            ->where('youth', false)
            ->where('played', false)
            ->where(fn (Builder $query) => $query->whereNull('home_team_id')->orWhereNull('away_team_id'))
            ->whereDate('scheduled_on', '<=', $season->current_date)
            ->orderBy('matchday')
            ->first();
    }

    public function userIsHome(): bool
    {
        return $this->home_team_id === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'played' => 'boolean',
            'youth' => 'boolean',
            'scheduled_on' => 'date',
        ];
    }
}
