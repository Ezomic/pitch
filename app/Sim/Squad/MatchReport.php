<?php

declare(strict_types=1);

namespace App\Sim\Squad;

final readonly class MatchReport
{
    /**
     * @param  list<MatchMoment>  $moments
     */
    public function __construct(
        public int $homeGoals,
        public int $awayGoals,
        public int $shots,
        public int $passesCompleted,
        public int $progressivePasses,
        public array $moments,
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
        ];
    }
}
