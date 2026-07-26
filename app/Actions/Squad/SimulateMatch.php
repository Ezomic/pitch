<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Squad;
use App\Sim\Engine\MatchEngine;
use App\Sim\Pitch\PitchReplay;
use App\Sim\Pitch\PositionalEngine;
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
        private readonly PositionalEngine $positional = new PositionalEngine,
        private readonly PitchReplay $replay = new PitchReplay,
    ) {}

    public function handle(Squad $squad, int $seed, ?TeamSetup $opponent = null, bool $positional = false): MatchReport
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

        // The watched match is simulated positionally for a rich replay; bulk
        // season/cup sims stay on the fast zone engine, which only needs scorelines.
        if ($positional || (string) config('pitch.engine') === 'positional') {
            return $this->replay->build(
                $this->positional->simulate(
                    $user->attackers(),
                    $opponent->attackers(),
                    $seed,
                    $user->formation,
                    $opponent->formation,
                ),
                $names,
            );
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
