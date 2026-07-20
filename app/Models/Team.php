<?php

declare(strict_types=1);

namespace App\Models;

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Roster;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $style
 * @property int $vision
 * @property int $passing
 * @property int $dribbling
 * @property int $finishing
 * @property int $tackling
 * @property int $pace
 */
#[Fillable(['name', 'style', 'vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

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
     * The team's flat profile applied to every formation slot.
     *
     * @return array<int, Attributes>
     */
    public function bySlot(): array
    {
        $attributes = $this->attributes();

        $bySlot = [];
        foreach (Roster::slots() as $slot) {
            $bySlot[$slot] = $attributes;
        }

        return $bySlot;
    }
}
