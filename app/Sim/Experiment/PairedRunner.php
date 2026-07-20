<?php

declare(strict_types=1);

namespace App\Sim\Experiment;

use App\Sim\Domain\Attributes;
use App\Sim\Engine\MatchEngine;
use App\Sim\Engine\Roster;

final class PairedRunner
{
    private const string LOW_LABEL = 'low-vision';

    private const string HIGH_LABEL = 'high-vision';

    public function __construct(
        private readonly MatchEngine $engine = new MatchEngine,
    ) {}

    /**
     * Run both arms across a shared seed sequence, so match i sees the identical
     * seed in each arm and match-to-match noise mostly cancels. Only vision
     * differs between the arms.
     */
    public function run(int $lowVision, int $highVision, int $matches, int $runSeed, int $sampleSize = 0): RunReport
    {
        $lowPlayers = Roster::build($this->template($lowVision));
        $highPlayers = Roster::build($this->template($highVision));

        $lowTally = new ArmTally(self::LOW_LABEL, $lowVision);
        $highTally = new ArmTally(self::HIGH_LABEL, $highVision);

        $samples = [];

        for ($i = 0; $i < $matches; $i++) {
            $seed = $runSeed + $i;

            $lowResult = $this->engine->simulate($lowPlayers, $seed);
            $highResult = $this->engine->simulate($highPlayers, $seed);

            $lowTally->add($lowResult);
            $highTally->add($highResult);

            if ($i < $sampleSize) {
                $samples[] = new SampledMatch(self::LOW_LABEL, $seed, $lowResult);
                $samples[] = new SampledMatch(self::HIGH_LABEL, $seed, $highResult);
            }
        }

        return new RunReport(
            $runSeed,
            $matches,
            $lowTally->summary(),
            $highTally->summary(),
            $samples,
        );
    }

    private function template(int $vision): Attributes
    {
        return new Attributes(
            vision: $vision,
            passing: 12,
            dribbling: 12,
            finishing: 12,
            tackling: 12,
            pace: 12,
        );
    }
}
