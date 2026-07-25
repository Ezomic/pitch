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
     * @param  int  $goalkeeping  the keeper's shot-stopping, 0..100
     */
    public function __construct(
        public array $bySlot,
        public Formation $formation,
        public Mentality $mentality,
        public int $goalkeeping = 55,
    ) {}

    /**
     * A neutral opponent: an average side in a balanced shape with an average
     * keeper.
     */
    public static function baseline(): self
    {
        $bySlot = [];
        foreach (Formation::balanced()->slots() as $slot) {
            $bySlot[$slot] = new Attributes(55, 55, 55, 55, 55, 55);
        }

        return new self($bySlot, Formation::balanced(), Mentality::Balanced, 55);
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
        return Defense::fromAttributes($this->bySlot, $this->formation, $this->mentality->defenceBias(), $this->goalkeeping);
    }

    public function attackBias(): float
    {
        return $this->mentality->attackBias();
    }
}
