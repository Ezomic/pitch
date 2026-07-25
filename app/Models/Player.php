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
 * @property bool $is_free_agent
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
 * @property int $handling
 * @property int $fitness
 * @property int $form
 * @property string|null $trait
 * @property int $injured_weeks
 * @property int $yellow_cards
 * @property int $suspended_weeks
 * @property int $contract_years
 * @property bool $on_loan
 * @property int $loan_weeks_remaining
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'is_free_agent', 'name', 'position', 'age', 'potential', 'is_youth', 'training_focus', 'vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace', 'handling', 'fitness', 'form', 'trait', 'injured_weeks', 'yellow_cards', 'suspended_weeks', 'contract_years', 'on_loan', 'loan_weeks_remaining'])]
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    /** The six trainable attributes; a prospect can focus its development on one. */
    public const array ATTRIBUTES = ['vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace'];

    /**
     * Signature traits, each a standing bonus to the attributes it names, applied
     * on top of raw ability whenever the player takes the field.
     *
     * @var array<string, array<string, int>>
     */
    public const array TRAITS = [
        'clinical' => ['finishing' => 10],
        'playmaker' => ['vision' => 8, 'passing' => 6],
        'pacey' => ['pace' => 12],
        'enforcer' => ['tackling' => 10],
        'dribbler' => ['dribbling' => 10],
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ability at or above this, or turning eighteen, makes a prospect first-team ready. */
    public const int PROMOTION_OVERALL = 70;

    /** Length of a fresh contract, in seasons, and the term a renewal restores. */
    public const int DEFAULT_CONTRACT_YEARS = 3;

    /** Weeks a loaned prospect spends away, and the ceiling lift it comes back with. */
    public const int LOAN_WEEKS = 12;

    public const int LOAN_RETURN_POTENTIAL = 3;

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
            ->where('is_free_agent', false)
            ->where('injured_weeks', 0)
            ->where('suspended_weeks', 0)
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
     * The attributes a player actually takes into a match: raw ability plus any
     * trait bonus, scaled by condition. Fitness runs 0.70 (spent) to 1.00
     * (fresh); form adds up to ±15%. Full fitness, neutral form and no trait
     * leave the attributes untouched.
     */
    public function matchAttributes(): Attributes
    {
        $attributes = $this->attributes();

        foreach (self::TRAITS[$this->trait] ?? [] as $attribute => $bonus) {
            $attributes = $attributes->plus($attribute, $bonus);
        }

        return $attributes->scaled($this->conditionFactor());
    }

    /**
     * A keeper's effective shot-stopping this match: raw handling scaled by
     * condition, so a tired or out-of-form keeper lets more through.
     */
    public function keeperRating(): int
    {
        return max(1, min(100, (int) round($this->handling * $this->conditionFactor())));
    }

    /**
     * A player's set-piece threat as a taker: an even blend of delivery (passing)
     * and the finish (finishing), scaled by condition.
     */
    public function setPieceRating(): int
    {
        $base = ($this->passing + $this->finishing) / 2;

        return max(1, min(100, (int) round($base * $this->conditionFactor())));
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
            $score += ($attr / 100) ** 2;
        }

        return max(1, (int) round($score * 10));
    }

    /**
     * Weekly wage, in the same units as the transfer bank. Derived from value so a
     * better player always costs more to keep, and a squad of stars runs a heavier
     * bill against the club's income.
     */
    public function weeklyWage(): int
    {
        return max(1, (int) round($this->value() / 10));
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
            'is_free_agent' => 'boolean',
            'handling' => 'integer',
            'fitness' => 'integer',
            'form' => 'integer',
            'injured_weeks' => 'integer',
            'yellow_cards' => 'integer',
            'suspended_weeks' => 'integer',
            'contract_years' => 'integer',
            'on_loan' => 'boolean',
            'loan_weeks_remaining' => 'integer',
        ];
    }
}
