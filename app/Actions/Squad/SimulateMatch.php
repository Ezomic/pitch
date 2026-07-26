<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Squad;
use App\Sim\Engine\MatchEngine;
use App\Sim\Squad\MatchLineups;
use App\Sim\Squad\MatchNarrator;
use App\Sim\Squad\MatchReport;
use App\Sim\Squad\TeamSetup;

class SimulateMatch
{
    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
        private readonly MatchNarrator $narrator = new MatchNarrator,
        private readonly MatchLineups $lineups = new MatchLineups,
    ) {}

    public function handle(Squad $squad, int $seed, ?TeamSetup $opponent = null): MatchReport
    {
        $user = $squad->setup();
        $opponent ??= TeamSetup::baseline();

        $names = [];
        foreach ($squad->assignments()->with('player')->get() as $assignment) {
            $names[$assignment->slot] = $assignment->player->name;
        }
        foreach ($user->formation->slots() as $slot) {
            $names[$slot] ??= "Slot {$slot}";
        }

        $attack = $this->engine->simulate(
            $user->attackers(),
            $seed,
            $opponent->defence(),
            $user->formation,
            $user->attackBias(),
        );

        $defence = $this->engine->simulate(
            $opponent->attackers(),
            $seed,
            $user->defence(),
            $opponent->formation,
            $opponent->attackBias(),
        );

        $lineups = $this->lineups->build($user->formation, $opponent->formation, $names);

        return $this->narrator->narrate($attack, $defence->goals, $names, $defence, $lineups);
    }
}
