<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Player;
use App\Models\SquadPlayer;

/**
 * Settle the condition consequences of a played match. Everyone who featured
 * loses fitness; their form swings with the result (win up, loss down) and each
 * scorer gets an extra lift. Form is clamped to its range and never runs away. A
 * player run into the ground can break down and drop out of the XI to recover.
 */
class ApplyMatchCondition
{
    /** Below this fitness a played player risks an injury; empty means certain. */
    private const int INJURY_FITNESS = 30;

    /** A committed tackler (this hard or harder) picks up a booking each match. */
    private const int BOOKING_TACKLING = 80;

    /** Yellow cards that trigger a one-week suspension, then reset. */
    private const int YELLOW_LIMIT = 3;

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
            $update = ['form' => max(Player::FORM_MIN, min(Player::FORM_MAX, $player->form + $formDelta))];

            if (isset($featured[$player->id])) {
                $fitness = max(0, $player->fitness - Player::MATCH_DRAIN);
                $update['fitness'] = $fitness;

                if ($this->breaksDown($fitness)) {
                    $update['injured_weeks'] = random_int(1, 3);
                    SquadPlayer::query()->where('player_id', $player->id)->delete();
                }

                if ($player->tackling >= self::BOOKING_TACKLING) {
                    $yellows = $player->yellow_cards + 1;

                    if ($yellows >= self::YELLOW_LIMIT) {
                        $update['yellow_cards'] = 0;
                        $update['suspended_weeks'] = 1;
                        SquadPlayer::query()->where('player_id', $player->id)->delete();
                    } else {
                        $update['yellow_cards'] = $yellows;
                    }
                }
            }

            $player->forceFill($update)->save();
        }
    }

    /** An exhausted player (no fitness left) is certain to break down; a merely
     * tired one has a chance rising as fitness falls toward the threshold. */
    private function breaksDown(int $fitness): bool
    {
        if ($fitness <= 0) {
            return true;
        }

        return $fitness < self::INJURY_FITNESS && random_int(1, 100) <= self::INJURY_FITNESS - $fitness;
    }
}
