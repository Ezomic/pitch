<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

/**
 * The per-seed counts behind the realism ratchet's known-off metrics, recorded
 * once so a later run can be compared with them seed by seed.
 *
 * A mean over a few hundred seeds still wanders by more than the distance a
 * known-off metric is allowed to move, so a change that only re-draws part of a
 * match looks worse than it is. Seed i plays the same first minutes before and
 * after such a change, so the per-seed difference carries far less noise than
 * either mean does, the way PairedRunner pairs its two arms. shift() returns
 * that difference and its standard error.
 *
 * @phpstan-type Row array<string, int>
 */
final readonly class RealismBaseline
{
    /** Seeds 1..SEEDS are simulated and recorded. */
    public const int SEEDS = 200;

    /**
     * label => [numerator column, denominator column]. Without a denominator the
     * metric is a per-match mean; with one it is a percentage of the totals.
     */
    private const array METRICS = [
        'Shots on target' => ['shotsOnTarget', null],
        'Pass completion %' => ['passesCompleted', 'passes'],
        'Crosses' => ['crosses', null],
        'Fouls' => ['fouls', null],
    ];

    /** @var list<string> */
    private const array COLUMNS = ['shotsOnTarget', 'passes', 'passesCompleted', 'crosses', 'fouls'];

    /**
     * @param  list<Row>  $rows  one per seed, seed 1 first
     */
    public function __construct(private array $rows) {}

    /** Resolved off this file rather than base_path(), so the sim stays framework-free. */
    public static function path(): string
    {
        return dirname(__DIR__, 3).'/tests/Fixtures/realism-baseline.json';
    }

    public static function recorded(): self
    {
        $path = self::path();

        if (! is_file($path)) {
            throw new \RuntimeException('No realism baseline recorded. Run: php artisan pitch:realism-baseline --write');
        }

        /** @var array{columns: list<string>, rows: list<list<int>>} $payload */
        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return new self(array_map(
            fn (array $values): array => array_combine($payload['columns'], $values),
            $payload['rows'],
        ));
    }

    /**
     * @param  list<MatchMetrics>  $perSeed
     */
    public static function from(array $perSeed): self
    {
        return new self(array_map(self::row(...), $perSeed));
    }

    public function record(): void
    {
        $rows = array_map(
            fn (array $row): string => json_encode(array_map(fn (string $column): int => $row[$column], self::COLUMNS), JSON_THROW_ON_ERROR),
            $this->rows,
        );

        file_put_contents(self::path(), sprintf(
            "{\n    \"columns\": %s,\n    \"rows\": [\n        %s\n    ]\n}\n",
            json_encode(self::COLUMNS, JSON_THROW_ON_ERROR),
            implode(",\n        ", $rows),
        ));
    }

    public function seeds(): int
    {
        return count($this->rows);
    }

    /** The metric over the recorded seeds, in the unit the report uses. */
    public function value(string $label): float
    {
        [$numerator, $denominator] = self::METRICS[$label];

        $top = array_sum(array_column($this->rows, $numerator));

        return $denominator === null
            ? $top / count($this->rows)
            : $top / array_sum(array_column($this->rows, $denominator)) * 100;
    }

    /**
     * How far a fresh run of the same seeds has moved the metric from the
     * recorded one, and the standard error of that move. A percentage is
     * linearised around the recorded ratio so that it too is a mean of
     * per-seed differences.
     *
     * @param  list<MatchMetrics>  $current  the same seeds, in the same order
     * @return array{delta: float, error: float}
     */
    public function shift(string $label, array $current): array
    {
        $n = count($this->rows);

        if (count($current) !== $n || $n < 2) {
            throw new \InvalidArgumentException(sprintf('Expected %d seeds to compare, got %d.', $n, count($current)));
        }

        [$numerator, $denominator] = self::METRICS[$label];
        $now = array_map(self::row(...), $current);
        $ratio = $denominator === null ? 0.0 : $this->value($label) / 100;
        $scale = $denominator === null ? 1.0 : 100 / (array_sum(array_column($now, $denominator)) / $n);

        $differences = [];
        foreach ($this->rows as $i => $before) {
            $differences[] = $this->contribution($now[$i], $numerator, $denominator, $ratio)
                - $this->contribution($before, $numerator, $denominator, $ratio);
        }

        $mean = array_sum($differences) / $n;
        $variance = array_sum(array_map(fn (float $d): float => ($d - $mean) ** 2, $differences)) / ($n - 1);

        return ['delta' => $mean * $scale, 'error' => sqrt($variance / $n) * $scale];
    }

    /**
     * @param  Row  $row
     */
    private function contribution(array $row, string $numerator, ?string $denominator, float $ratio): float
    {
        return $denominator === null
            ? (float) $row[$numerator]
            : $row[$numerator] - $ratio * $row[$denominator];
    }

    /**
     * @return Row
     */
    private static function row(MatchMetrics $match): array
    {
        return [
            'shotsOnTarget' => $match->shotsOnTarget,
            'passes' => $match->passes,
            'passesCompleted' => $match->passesCompleted,
            'crosses' => $match->crosses,
            'fouls' => $match->fouls,
        ];
    }
}
