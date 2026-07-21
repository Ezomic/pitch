<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;

/**
 * Settle the condition consequences of a played match. Everyone who featured
 * loses fitness; their form swings with the result (win up, loss down) and each
 * scorer gets an extra lift. Form is clamped to its range and never runs away.
 */
class ApplyMatchCondition
{
    /**
     * @param  list<int>  $featuredPlayerIds  the XI that played (drained + result-nudged)
     * @param  list<int>  $scoringPlayerIds  players who scored (an extra form lift)
     * @param  int  $stakes  multiplies the result's form swing (a derby raises it)
     */
    public function handle(array $featuredPlayerIds, int $goalsFor, int $goalsAgainst, array $scoringPlayerIds = [], int $stakes = 1): void
    {
        $resultNudge = ($goalsFor <=> $goalsAgainst) * $stakes;

        $affected = array_values(array_unique([...$featuredPlayerIds, ...$scoringPlayerIds]));

        if ($affected === []) {
            return;
        }

        $featured = array_flip($featuredPlayerIds);
        $scorerCounts = array_count_values($scoringPlayerIds);

        foreach (Player::query()->whereIn('id', $affected)->get() as $player) {
            $formDelta = $resultNudge + ($scorerCounts[$player->id] ?? 0);

            $form = max(Player::FORM_MIN, min(Player::FORM_MAX, $player->form + $formDelta));
            $fitness = isset($featured[$player->id])
                ? max(0, $player->fitness - Player::MATCH_DRAIN)
                : $player->fitness;

            $player->forceFill(['form' => $form, 'fitness' => $fitness])->save();
        }
    }
}
