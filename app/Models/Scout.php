<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScoutStatus;
use Database\Factories\ScoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $rating
 * @property ScoutStatus $status
 * @property Carbon|null $next_delivery_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'rating', 'status', 'next_delivery_on'])]
class Scout extends Model
{
    /** @use HasFactory<ScoutFactory> */
    use HasFactory;

    /** Higher-rated scouts cost more to bring on, in millions. */
    public function cost(): int
    {
        return $this->rating * 5;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Scout>  $query
     */
    public function scopeScouting(Builder $query): void
    {
        $query->where('status', ScoutStatus::Scouting);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ScoutStatus::class,
            'next_delivery_on' => 'date',
        ];
    }
}
