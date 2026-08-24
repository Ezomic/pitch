<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A manager's seat in a league career: which club they run, and whether they
 * own the league. A club nobody holds a seat for is played by the AI, which is
 * how a league can start with two humans and eleven computer sides.
 *
 * @property int $id
 * @property int $career_id
 * @property int $user_id
 * @property int|null $team_id
 * @property string $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['career_id', 'user_id', 'team_id', 'role'])]
class CareerMembership extends Model
{
    /** Runs the league: invites, removes and sets it up. */
    public const string OWNER = 'owner';

    public const string MEMBER = 'member';

    /**
     * @return BelongsTo<Career, $this>
     */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isOwner(): bool
    {
        return $this->role === self::OWNER;
    }
}
