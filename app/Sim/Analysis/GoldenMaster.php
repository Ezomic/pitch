<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

use App\Sim\Domain\Attributes;
use App\Sim\Engine\Formation;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Mentality;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PositionalEngine;
use App\Sim\Squad\FixtureResolver;
use App\Sim\Squad\TeamSetup;

/**
 * A recorded fingerprint of what the engines actually produce, seed by seed.
 *
 * RngTest proves the random stream is stable and ResumableEngineTest proves a
 * paused match resumes exactly, but neither notices when a calibration change
 * quietly rewrites every historical result. This hashes the full output of each
 * simulation path so that any such change shows up as a diff on a committed
 * file rather than passing in silence.
 *
 * A moving digest is not a failure in itself: a calibration ticket is supposed
 * to move it. What matters is that moving it is deliberate, reviewable, and
 * lands in the same commit as the change that caused it.
 */
final class GoldenMaster
{
    /** How many seeds each path is fingerprinted over. */
    public const int SEEDS = 8;

    /** The even rating both positional sides are built at. */
    public const int RATING = 72;

    public function __construct(
        private readonly PositionalEngine $positional = new PositionalEngine,
        private readonly MatchEngine $openPlay = new MatchEngine,
        private readonly FixtureResolver $fixtures = new FixtureResolver,
    ) {}

    /** Resolved off this file rather than base_path(), so the sim stays framework-free. */
    public static function path(): string
    {
        return dirname(__DIR__, 3).'/tests/Fixtures/golden-master.json';
    }

    /**
     * Every simulation path fingerprinted across the fixed seed range.
     *
     * @return array<string, string> "path:seed" => sha256 digest
     */
    public function digests(): array
    {
        $digests = [];

        for ($seed = 1; $seed <= self::SEEDS; $seed++) {
            $digests["positional:{$seed}"] = $this->positionalDigest($seed);
            $digests["open-play:{$seed}"] = $this->openPlayDigest($seed);
            $digests["fixture:{$seed}"] = $this->fixtureDigest($seed);
        }

        return $digests;
    }

    /**
     * The digests as they were last recorded.
     *
     * @return array<string, string>
     */
    public function recorded(): array
    {
        $path = self::path();

        if (! is_file($path)) {
            return [];
        }

        /** @var array{digests?: array<string, string>} $payload */
        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $payload['digests'] ?? [];
    }

    /**
     * Record the current digests, replacing whatever was there.
     *
     * @param  array<string, string>  $digests
     */
    public function record(array $digests): void
    {
        $payload = [
            'seeds' => self::SEEDS,
            'rating' => self::RATING,
            'digests' => $digests,
        ];

        file_put_contents(
            self::path(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n",
        );
    }

    /**
     * The keys whose digest no longer matches what was recorded, plus any key
     * that appeared or disappeared entirely.
     *
     * @param  array<string, string>  $current
     * @param  array<string, string>  $recorded
     * @return list<string>
     */
    public function diverged(array $current, array $recorded): array
    {
        $keys = array_unique([...array_keys($recorded), ...array_keys($current)]);
        sort($keys);

        return array_values(array_filter(
            $keys,
            fn (string $key): bool => ($recorded[$key] ?? null) !== ($current[$key] ?? null),
        ));
    }

    /**
     * The positional engine's whole output: the score, every event and every
     * per-tick frame, so a change in position maths is caught as readily as a
     * change in the scoreline.
     */
    private function positionalDigest(int $seed): string
    {
        $team = Roster::build(new Attributes(self::RATING, self::RATING, self::RATING, self::RATING, self::RATING, self::RATING));
        $result = $this->positional->simulate($team, $team, $seed);

        $context = hash_init('sha256');
        hash_update($context, "score={$result->homeGoals}:{$result->awayGoals}\n");

        foreach ($result->events as $event) {
            hash_update($context, $this->canonical($event->toArray())."\n");
        }

        foreach ($result->frames as $frame) {
            hash_update($context, $this->canonical($frame)."\n");
        }

        return hash_final($context);
    }

    /** The open-play engine's raw event stream for one attacking side. */
    private function openPlayDigest(int $seed): string
    {
        $home = $this->setup(self::RATING, '433', Mentality::Balanced);
        $away = $this->setup(self::RATING - 8, '532', Mentality::Defensive);

        $result = $this->openPlay->simulate(
            $home->attackers(),
            $seed,
            $away->defence(),
            $home->formation,
            $home->attackBias(),
        );

        $context = hash_init('sha256');
        foreach ($result->events as $event) {
            hash_update($context, $this->canonical($event->toArray())."\n");
        }

        return hash_final($context);
    }

    /** A full fixture between two deliberately unequal sides. */
    private function fixtureDigest(int $seed): string
    {
        $result = $this->fixtures->resolve(
            $this->setup(self::RATING, '433', Mentality::Balanced),
            $this->setup(self::RATING - 8, '532', Mentality::Defensive),
            $seed,
        );

        return hash('sha256', $this->canonical($result));
    }

    /**
     * A fixed, uneven side: attributes vary slot to slot off the base rating, so
     * the fingerprint would notice a change that only shows up when players
     * differ from one another.
     */
    private function setup(int $base, string $formationId, Mentality $mentality): TeamSetup
    {
        $formation = Formation::fromId($formationId);

        $bySlot = [];
        foreach ($formation->slots() as $slot) {
            $bySlot[$slot] = new Attributes(
                $base + ($slot % 5),
                $base + ($slot % 3),
                $base - ($slot % 4),
                $base + ($slot % 7) - 3,
                $base - ($slot % 6) + 2,
                $base + ($slot % 2),
            );
        }

        return new TeamSetup($bySlot, $formation, $mentality, $base + 3, $base - 2);
    }

    /**
     * A stable text form of a value. Floats are written at full round-trip
     * precision with an explicit format rather than through json_encode, whose
     * float output depends on the serialize_precision ini setting and so could
     * differ between a dev machine and CI.
     */
    private function canonical(mixed $value): string
    {
        return match (true) {
            is_float($value) => sprintf('%.17g', $value),
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_string($value) => '"'.$value.'"',
            $value === null => 'null',
            is_array($value) => $this->canonicalArray($value),
            default => throw new \InvalidArgumentException('Value cannot be fingerprinted: '.get_debug_type($value)),
        };
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function canonicalArray(array $value): string
    {
        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] = $key.'='.$this->canonical($item);
        }

        return '['.implode(',', $parts).']';
    }
}
