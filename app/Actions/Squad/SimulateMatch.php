<?php

declare(strict_types=1);

namespace App\Actions\Squad;

use App\Models\Squad;
use App\Sim\Domain\Attributes;
use App\Sim\Engine\Defense;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;
use App\Sim\Squad\MatchNarrator;
use App\Sim\Squad\MatchReport;

class SimulateMatch
{
    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
        private readonly MatchNarrator $narrator = new MatchNarrator,
    ) {}

    public function handle(Squad $squad, int $seed): MatchReport
    {
        $bySlot = [];
        $names = [];

        foreach ($squad->assignments()->with('player')->get() as $assignment) {
            $bySlot[$assignment->slot] = $assignment->player->attributes();
            $names[$assignment->slot] = $assignment->player->name;
        }

        foreach (Roster::slots() as $slot) {
            $bySlot[$slot] ??= new Attributes(10, 10, 10, 10, 10, 10);
            $names[$slot] ??= "Slot {$slot}";
        }

        $baseline = $this->baseline();

        $attack = $this->engine->simulate(
            Roster::fromAttributes($bySlot),
            $seed,
            Defense::fromAttributes($baseline),
        );

        $defence = $this->engine->simulate(
            Roster::fromAttributes($baseline),
            $seed,
            Defense::fromAttributes($bySlot),
        );

        return $this->narrator->narrate($attack, $defence->goals, $names);
    }

    /**
     * @return array<int, Attributes>
     */
    private function baseline(): array
    {
        $bySlot = [];
        foreach (Roster::slots() as $slot) {
            $bySlot[$slot] = new Attributes(11, 11, 11, 11, 11, 11);
        }

        return $bySlot;
    }
}
