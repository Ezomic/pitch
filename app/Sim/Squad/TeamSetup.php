<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Player;
use App\Sim\Engine\Defense;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Engine\Roster;

/**
 * A team's full tactical identity for a simulation: who plays where (attributes
 * on a formation) and how they play (mentality).
 */
final readonly class TeamSetup
{
    /**
     * @param  array<int, Attributes>  $bySlot  slot id => attributes
     */
    public function __construct(
        public array $bySlot,
        public Formation $formation,
        public Mentality $mentality,
    ) {}

    /**
     * A neutral opponent: an average side in a balanced shape.
     */
    public static function baseline(): self
    {
        $bySlot = [];
        foreach (Formation::balanced()->slots() as $slot) {
            $bySlot[$slot] = new Attributes(11, 11, 11, 11, 11, 11);
        }

        return new self($bySlot, Formation::balanced(), Mentality::Balanced);
    }

    /**
     * @return array<int, Player>
     */
    public function attackers(): array
    {
        return Roster::fromAttributes($this->bySlot, $this->formation);
    }

    public function defence(): Defense
    {
        return Defense::fromAttributes($this->bySlot, $this->formation, $this->mentality->defenceBias());
    }

    public function attackBias(): float
    {
        return $this->mentality->attackBias();
    }
}
