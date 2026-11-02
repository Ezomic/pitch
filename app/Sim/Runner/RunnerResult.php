<?php

declare(strict_types=1);

namespace App\Sim\Runner;

final readonly class RunnerResult
{
    /**
     * @param  array<string, mixed>  $stats
     * @param  list<array<string, mixed>>  $events
     */
    public function __construct(
        public string $version,
        public int $homeGoals,
        public int $awayGoals,
        public array $stats,
        public array $events,
    ) {}

    /**
     * @param  array<mixed>  $data
     *
     * @throws MatchRunnerException
     */
    public static function fromArray(array $data): self
    {
        $version = $data['version'] ?? null;
        $home = $data['home_goals'] ?? null;
        $away = $data['away_goals'] ?? null;
        $stats = $data['stats'] ?? [];
        $events = $data['events'] ?? [];

        if (! is_string($version) || $version === ''
            || ! is_int($home) || $home < 0
            || ! is_int($away) || $away < 0
            || ! is_array($stats) || ! is_array($events)) {
            throw new MatchRunnerException('The runner returned a result that does not match the schema.');
        }

        /** @var array<string, mixed> $stats */
        /** @var list<array<string, mixed>> $events */
        return new self($version, $home, $away, $stats, $events);
    }
}
