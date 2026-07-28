<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

/**
 * The home side's per-match figures with both sides even versus with one
 * attribute raised, and the change between them. If the engine is legible, the
 * change is clean and points the way the attribute should push.
 *
 * @phpstan-type Row array{label: string, control: float, treatment: float, delta: float, unit: string}
 */
final class ExperimentReport
{
    public function __construct(
        private readonly SideMetrics $control,
        private readonly SideMetrics $treatment,
    ) {}

    /**
     * @return list<Row>
     */
    public function rows(): array
    {
        return [
            $this->row('Goals for', fn (SideMetrics $m): float => $m->goalsFor / max(1, $m->matches)),
            $this->row('Goals against', fn (SideMetrics $m): float => $m->goalsAgainst / max(1, $m->matches)),
            $this->row('Shots', fn (SideMetrics $m): float => $m->shots / max(1, $m->matches)),
            $this->row('Conversion %', fn (SideMetrics $m): float => $m->goalsFor / max(1, $m->shots) * 100, '%'),
            $this->row('Pass completion %', fn (SideMetrics $m): float => $m->passesCompleted / max(1, $m->passes) * 100, '%'),
            $this->row('Possession %', fn (SideMetrics $m): float => $m->framesInPossession / max(1, $m->frames) * 100, '%'),
        ];
    }

    /**
     * @param  callable(SideMetrics): float  $metric
     * @return Row
     */
    private function row(string $label, callable $metric, string $unit = ''): array
    {
        $control = $metric($this->control);
        $treatment = $metric($this->treatment);

        return [
            'label' => $label,
            'control' => round($control, 2),
            'treatment' => round($treatment, 2),
            'delta' => round($treatment - $control, 2),
            'unit' => $unit,
        ];
    }
}
