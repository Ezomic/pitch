<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

/**
 * Per-match rates from a summed batch, each next to the range a real match would
 * sit in, so a run says plainly where the engine matches football and where it
 * drifts. Reference ranges are rough top-division per-match figures (both teams
 * combined) and are targets to calibrate toward, not hard limits.
 *
 * @phpstan-type Row array{label: string, value: float, low: float, high: float, unit: string, ok: bool}
 */
final class RealismReport
{
    /** @var array<string, array{float, float}> label => [low, high] */
    private const BANDS = [
        'Goals' => [2.4, 3.2],
        'Shots' => [22.0, 30.0],
        'Shots on target' => [7.5, 11.0],
        'Shot conversion %' => [9.0, 14.0],
        'Pass completion %' => [70.0, 88.0],
        'Crosses' => [14.0, 42.0],
        'Fouls' => [18.0, 28.0],
        'Corners' => [8.0, 12.0],
        'Home possession %' => [45.0, 55.0],
        'Final-third time %' => [12.0, 30.0],
    ];

    public function __construct(private readonly MatchMetrics $total) {}

    /**
     * @return list<Row>
     */
    public function rows(): array
    {
        $n = max(1, $this->total->matches);
        $frames = max(1, $this->total->frames);
        $shots = max(1, $this->total->shots);
        $passes = max(1, $this->total->passes);

        $values = [
            'Goals' => [$this->total->goals / $n, ''],
            'Shots' => [$this->total->shots / $n, ''],
            'Shots on target' => [$this->total->shotsOnTarget / $n, ''],
            'Shot conversion %' => [$this->total->goals / $shots * 100, '%'],
            'Pass completion %' => [$this->total->passesCompleted / $passes * 100, '%'],
            'Crosses' => [$this->total->crosses / $n, ''],
            'Fouls' => [$this->total->fouls / $n, ''],
            'Corners' => [$this->total->corners / $n, ''],
            'Home possession %' => [$this->total->framesHome / $frames * 100, '%'],
            'Final-third time %' => [$this->total->framesFinalThird / $frames * 100, '%'],
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
