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
     * @param  int  $setPiece  the side's dead-ball strength, 0..100
     * @param  array<int, int>  $fitness  slot id => season fitness, 0..100, which sets how much is in his legs at kickoff
     */
    public function __construct(
        public array $bySlot,
        public Formation $formation,
        public Mentality $mentality,
        public int $goalkeeping = 55,
        public int $setPiece = 50,
        public array $fitness = [],
    ) {}

    /**
     * A neutral opponent: an average side in a balanced shape with an average
     * keeper and set-piece threat.
     */
    public static function baseline(): self
    {
        $bySlot = [];
        foreach (Formation::balanced()->slots() as $slot) {
            $bySlot[$slot] = new Attributes(55, 55, 55, 55, 55, 55);
        }

        return new self($bySlot, Formation::balanced(), Mentality::Balanced, 55, 50);
    }

    /**
     * @return array<int, Player>
     */
    public function attackers(): array
    {
        return Roster::fromAttributes($this->bySlot, $this->formation, $this->fitness);
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
