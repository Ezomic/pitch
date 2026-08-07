<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

/** The summed outcome of a batch of league fixtures. */
final readonly class LeagueMetrics
{
    public function __construct(
        public int $matches,
        public int $homeGoals,
        public int $awayGoals,
        public int $homeWins,
        public int $draws,
        public int $awayWins,
        public int $goalless,
    ) {}

    public static function zero(): self
    {
        return new self(0, 0, 0, 0, 0, 0, 0);
    }

    public function add(int $home, int $away): self
    {
        return new self(
            $this->matches + 1,
            $this->homeGoals + $home,
            $this->awayGoals + $away,
            $this->homeWins + ($home > $away ? 1 : 0),
            $this->draws + ($home === $away ? 1 : 0),
            $this->awayWins + ($home < $away ? 1 : 0),
            $this->goalless + ($home + $away === 0 ? 1 : 0),
        );
    }
}
