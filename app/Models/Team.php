<?php

declare(strict_types=1);

namespace App\Models;

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Engine\Roster;
use App\Sim\Squad\TeamSetup;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $style
 * @property bool $is_youth
 * @property bool $is_derby
 * @property string $formation
 * @property string $mentality
 * @property int $vision
 * @property int $passing
 * @property int $dribbling
 * @property int $finishing
 * @property int $tackling
 * @property int $pace
 */
#[Fillable(['name', 'style', 'is_youth', 'is_derby', 'formation', 'mentality', 'vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_youth' => 'boolean',
            'is_derby' => 'boolean',
        ];
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

    public function setup(): TeamSetup
    {
        return new TeamSetup(
            $this->bySlot(),
            Formation::fromId($this->formation),
            Mentality::fromId($this->mentality),
        );
    }
}
