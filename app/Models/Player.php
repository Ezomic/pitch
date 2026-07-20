<?php

declare(strict_types=1);

namespace App\Models;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Position;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'position', 'age', 'potential', 'is_youth', 'vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace'])]
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

    /** A young prospect still short of its ceiling, so still improving. */
    public function isDeveloping(): bool
    {
        return $this->is_youth && $this->attributes()->overall() < $this->potential;
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
