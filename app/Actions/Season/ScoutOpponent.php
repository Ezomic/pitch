<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Squad;
use App\Models\Team;
use App\Sim\Squad\SquadEvaluator;
use App\Sim\Squad\SquadProfile;
use App\Sim\Squad\TeamSetup;

/**
 * A pre-match scouting report. It reads the opponent's own tendencies (their
 * profile against a neutral baseline) and, more usefully, how the user's current
 * squad projects specifically against that opponent, so the user can counter-pick
 * formation and mentality before kickoff.
 */
class ScoutOpponent
{
    public function __construct(
        private readonly SquadEvaluator $evaluator = new SquadEvaluator,
    ) {}

    /**
     * @return array{opponent: array<string, float>, matchup: array<string, float>}
     */
    public function handle(Squad $userSquad, Team $opponent): array
    {
        $opponentSetup = $opponent->setup();

        return [
            'opponent' => $this->profile($this->evaluator->evaluate($opponentSetup, TeamSetup::baseline())),
            'matchup' => $this->profile($this->evaluator->evaluate($userSquad->setup(), $opponentSetup)),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function profile(SquadProfile $profile): array
    {
        return [
            'meanDecisionGap' => $profile->meanDecisionGap,
            'progressivePassShare' => $profile->progressivePassShare,
            'chancesPer90' => $profile->chancesPer90,
            'goalsPer90' => $profile->goalsPer90,
            'chancesConcededPer90' => $profile->chancesConcededPer90,
            'goalsConcededPer90' => $profile->goalsConcededPer90,
        ];
    }
}
