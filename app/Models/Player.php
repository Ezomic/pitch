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
 * @property int $vision
 * @property int $passing
 * @property int $dribbling
 * @property int $finishing
 * @property int $tackling
 * @property int $pace
 * @property int $fitness
 * @property int $form
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'position', 'age', 'potential', 'is_youth', 'vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace', 'fitness', 'form'])]
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ability at or above this, or turning eighteen, makes a prospect first-team ready. */
    public const int PROMOTION_OVERALL = 14;

    /** Condition the season clock moves: a match tires, a week of rest recovers. */
    public const int MATCH_DRAIN = 18;

    public const int WEEKLY_RECOVERY = 12;

    public const int FITNESS_MAX = 100;

    /** Form is a small signed swing that mean-reverts toward zero. */
    public const int FORM_MIN = -5;

    public const int FORM_MAX = 5;

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
     * The attributes a player actually takes into a match: their raw ability
     * scaled by condition. Fitness runs 0.70 (spent) to 1.00 (fresh); form adds
     * up to ±15%. Full fitness and neutral form leave the attributes untouched.
     */
    public function matchAttributes(): Attributes
    {
        return $this->attributes()->scaled($this->conditionFactor());
    }

    public function conditionFactor(): float
    {
        $fitnessFactor = 0.70 + 0.30 * ($this->fitness / self::FITNESS_MAX);
        $formFactor = 1.0 + 0.03 * $this->form;

        return $fitnessFactor * $formFactor;
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
            'fitness' => 'integer',
            'form' => 'integer',
        ];
    }
}
