<?php

declare(strict_types=1);

namespace App\Models;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Position;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property Position $position
 * @property int $age
 * @property int $potential
 * @property bool $is_youth
 * @property string|null $training_focus
 * @property int $vision
 * @property int $passing
 * @property int $dribbling
 * @property int $finishing
 * @property int $tackling
 * @property int $pace
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'position', 'age', 'potential', 'is_youth', 'training_focus', 'vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace'])]
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    /** The six trainable attributes; a prospect can focus its development on one. */
    public const array ATTRIBUTES = ['vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ability at or above this, or turning eighteen, makes a prospect first-team ready. */
    public const int PROMOTION_OVERALL = 14;

    public function overall(): int
    {
        return $this->attributes()->overall();
    }

    /** A young prospect still short of its ceiling, so still improving. */
    public function isDeveloping(): bool
    {
        return $this->is_youth && $this->overall() < $this->potential;
    }

    /** A prospect good enough, or old enough, to step up to the first team. */
    public function isPromotable(): bool
    {
        return $this->is_youth && ($this->age >= 18 || $this->overall() >= self::PROMOTION_OVERALL);
    }

    /**
     * Players a user can field: the shared senior pool plus their own promoted
     * academy graduates, never another club's players nor anyone's youth.
     *
     * @param  Builder<Player>  $query
     */
    public function scopeSelectableFor(Builder $query, int $userId): void
    {
        $query->where('is_youth', false)
            ->where(fn (Builder $scoped) => $scoped->whereNull('user_id')->orWhere('user_id', $userId));
    }

    public function attributes(): Attributes
    {
        return new Attributes(
            vision: $this->vision,
            passing: $this->passing,
            dribbling: $this->dribbling,
            finishing: $this->finishing,
            tackling: $this->tackling,
            pace: $this->pace,
        );
    }

    /**
     * A convex price derived from the six attributes, so elite players cost
     * disproportionately more than average ones.
     */
    public function value(): int
    {
        $attrs = [$this->vision, $this->passing, $this->dribbling, $this->finishing, $this->tackling, $this->pace];

        $score = 0.0;
        foreach ($attrs as $attr) {
            $score += ($attr / 20) ** 2;
        }

        return max(1, (int) round($score * 10));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => Position::class,
            'age' => 'integer',
            'potential' => 'integer',
            'is_youth' => 'boolean',
        ];
    }
}
