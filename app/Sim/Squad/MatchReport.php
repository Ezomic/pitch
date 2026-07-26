<?php

declare(strict_types=1);

namespace App\Sim\Squad;

final readonly class MatchReport
{
    /**
     * @param  list<MatchMoment>  $moments
     * @param  list<array<string, mixed>>  $timeline  ordered ball-position frames for the 2D replay
     * @param  list<array<string, mixed>>  $lineups  both teams' formation positions for the 2D replay
     * @param  list<array{b: int, p: list<array{float, float}>}>  $positions  per-frame player positions for the 2D replay
     */
    public function __construct(
        public int $homeGoals,
        public int $awayGoals,
        public int $shots,
        public int $passesCompleted,
        public int $progressivePasses,
        public array $moments,
        public array $timeline = [],
        public array $lineups = [],
        public array $positions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'homeGoals' => $this->homeGoals,
            'awayGoals' => $this->awayGoals,
            'shots' => $this->shots,
            'passesCompleted' => $this->passesCompleted,
            'progressivePasses' => $this->progressivePasses,
            'moments' => array_map(fn (MatchMoment $moment) => $moment->toArray(), $this->moments),
            'timeline' => $this->timeline,
            'lineups' => $this->lineups,
            'positions' => $this->positions,
        ];
    }
}
