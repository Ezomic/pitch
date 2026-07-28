<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\EventType;
use App\Sim\Engine\Roster;
use App\Sim\Pitch\PitchResult;
use App\Sim\Pitch\PositionalEngine;
use InvalidArgumentException;

/**
 * The legibility experiment: run the same batch of seeds twice, once with both
 * sides even and once with a single attribute raised on the home side, and read
 * the home side's output each way. Same seeds both runs, so the delta isolates
 * the one attribute rather than sampling noise.
 */
final class ExperimentHarness
{
    private const ATTRIBUTES = ['vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace'];

    public function __construct(
        private readonly PositionalEngine $engine = new PositionalEngine,
    ) {}

    /**
     * @return array{control: SideMetrics, treatment: SideMetrics}
     */
    public function run(string $attribute, int $delta, int $matches, int $rating = 72): array
    {
        if (! in_array($attribute, self::ATTRIBUTES, true)) {
            throw new InvalidArgumentException("Unknown attribute: {$attribute}");
        }

        $even = $this->attributes($rating);
        $boosted = $this->attributes($rating, $attribute, $delta);

        $control = SideMetrics::zero();
        $treatment = SideMetrics::zero();

        for ($seed = 1; $seed <= $matches; $seed++) {
            $control = $control->add($this->homeMetrics(
                $this->engine->simulate(Roster::build($even), Roster::build($even), $seed),
            ));
            $treatment = $treatment->add($this->homeMetrics(
                $this->engine->simulate(Roster::build($boosted), Roster::build($even), $seed),
            ));
        }

        return ['control' => $control, 'treatment' => $treatment];
    }

    private function homeMetrics(PitchResult $result): SideMetrics
    {
        $shots = 0;
        $passes = 0;
        $passesCompleted = 0;

        foreach ($result->events as $event) {
            if ($event->actorId >= 100) {
                continue; // away side
            }

            if ($event->type->isShot()) {
                $shots++;
            } elseif ($event->type === EventType::Pass) {
                $passes++;
                $event->success && $passesCompleted++;
            }
        }

        $frames = count($result->frames);
        $inPossession = 0;
        foreach ($result->frames as $frame) {
            if ($frame['s'] === 0) {
                $inPossession++;
            }
        }

        return new SideMetrics(
            matches: 1,
            goalsFor: $result->homeGoals,
            goalsAgainst: $result->awayGoals,
            shots: $shots,
            passes: $passes,
            passesCompleted: $passesCompleted,
            frames: $frames,
            framesInPossession: $inPossession,
        );
    }

    private function attributes(int $rating, ?string $bump = null, int $delta = 0): Attributes
    {
        $values = array_fill_keys(self::ATTRIBUTES, $rating);

        if ($bump !== null) {
            $values[$bump] = max(1, min(100, $rating + $delta));
        }

        return new Attributes(
            $values['vision'],
            $values['passing'],
            $values['dribbling'],
            $values['finishing'],
            $values['tackling'],
            $values['pace'],
        );
    }
}
