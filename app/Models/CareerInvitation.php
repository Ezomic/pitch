<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * A link that lets one manager into a league.
 *
 * Single use and time limited: an invitation that has been taken, or has run
 * out, opens nothing. It is kept afterwards rather than deleted so a league can
 * show who came in on which invitation.
 *
 * @property int $id
 * @property int $career_id
 * @property int $created_by
 * @property string $token
 * @property Carbon|null $expires_at
 * @property int|null $claimed_by
 * @property Carbon|null $claimed_at
 */
#[Fillable(['career_id', 'created_by', 'token', 'expires_at', 'claimed_by', 'claimed_at'])]
class CareerInvitation extends Model
{
    /**
     * @return BelongsTo<Career, $this>
     */
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function isOpen(): bool
    {
        return $this->claimed_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function status(): string
    {
        return match (true) {
            $this->claimed_at !== null => 'used',
            $this->expires_at !== null && $this->expires_at->isPast() => 'expired',
            default => 'open',
        };
    }

    public function claim(User $user): void
    {
        $this->forceFill(['claimed_by' => $user->id, 'claimed_at' => Date::now()])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }
}
