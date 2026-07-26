<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Engine\MatchResult;

final class MatchNarrator
{
    public function __construct(
        private readonly MatchCommentary $commentary = new MatchCommentary,
        private readonly MatchTimeline $timeline = new MatchTimeline,
        private readonly PlayerMotion $motion = new PlayerMotion,
    ) {}

    /**
     * Turn one attacking match into a readable report: a scoreline plus a curated,
     * minute-ordered highlights feed rather than every pass. When the opponent's
     * leg is supplied, a 2D-replay timeline is folded in from both legs.
     *
     * @param  array<int, string>  $names  slot id => player name
     * @param  list<array{s: int, slot: int, name: string|null, x: float, y: float, gk: bool}>  $lineups  both teams' formation positions
     */
    public function narrate(MatchResult $attack, int $opponentGoals, array $names, ?MatchResult $defence = null, array $lineups = []): MatchReport
    {
        $timeline = $defence !== null ? $this->timeline->build($attack, $defence, $names, $lineups) : [];
        $positions = $timeline !== [] && $lineups !== [] ? $this->motion->build($timeline, $lineups) : [];

        return new MatchReport(
            homeGoals: $attack->goals,
            awayGoals: $opponentGoals,
            shots: $attack->shots,
            passesCompleted: $attack->passesCompleted,
            progressivePasses: $attack->progressivePasses,
            moments: $this->feed($attack, $names),
            timeline: $timeline,
            lineups: $defence !== null ? $lineups : [],
            positions: $positions,
        );
    }

    /**
     * The curated highlights of one attacking side, in minute order.
     *
     * @param  array<int, string>  $names  slot id => player name
     * @return list<MatchMoment>
     */
    public function feed(MatchResult $attack, array $names): array
    {
        $moments = [];

        foreach ($attack->events as $index => $event) {
            $moment = $this->commentary->moment($event, $index, $names);

            if ($moment !== null) {
                $moments[] = $moment;
            }
        }

        return $moments;
    }
}
