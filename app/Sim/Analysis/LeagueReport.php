<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

/**
 * What a batch of auto-resolved fixtures looks like next to a real division.
 * Reference ranges are rough top-division figures and are targets to calibrate
 * toward, not hard limits.
 *
 * @phpstan-type Row array{label: string, value: float, low: float, high: float, unit: string, ok: bool}
 */
final class LeagueReport
{
    /** @var array<string, array{float, float}> label => [low, high] */
    private const BANDS = [
        'Goals per game' => [2.4, 3.2],
        'Home wins %' => [40.0, 50.0],
        'Draws %' => [22.0, 30.0],
        'Away wins %' => [24.0, 34.0],
        'Goalless %' => [4.0, 14.0],
    ];

    public function __construct(private readonly LeagueMetrics $total) {}

    /**
     * @return list<Row>
     */
    public function rows(): array
    {
        $n = max(1, $this->total->matches);

        $values = [
            'Goals per game' => [($this->total->homeGoals + $this->total->awayGoals) / $n, ''],
            'Home wins %' => [$this->total->homeWins / $n * 100, '%'],
            'Draws %' => [$this->total->draws / $n * 100, '%'],
            'Away wins %' => [$this->total->awayWins / $n * 100, '%'],
            'Goalless %' => [$this->total->goalless / $n * 100, '%'],
        ];

        $rows = [];
        foreach ($values as $label => [$value, $unit]) {
            [$low, $high] = self::BANDS[$label];
            $rows[] = [
                'label' => $label,
                'value' => round($value, 1),
                'low' => $low,
                'high' => $high,
                'unit' => $unit,
                'ok' => $value >= $low && $value <= $high,
            ];
        }

        return $rows;
    }
}
