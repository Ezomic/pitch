<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\CupTie;
use App\Models\Season;
use App\Models\Team;

/**
 * Draw the opening round of a knockout cup that runs alongside the league: the
 * user's club plus every senior rival, paired off after a deterministic shuffle.
 * An odd entrant gets a bye into the next round. One round is then resolved a
 * week until a champion is left.
 */
class DrawCup
{
    public function handle(Season $season): void
    {
        if ($season->cupTies()->exists()) {
            return;
        }

        $entrants = $this->shuffled($season);

        $slot = 0;
        for ($i = 0; $i < count($entrants); $i += 2) {
            $home = $entrants[$i];
            $away = $entrants[$i + 1] ?? null;

            $season->cupTies()->create([
                'round' => 1,
                'slot' => $slot,
                'home' => $home,
                'away' => $away,
                // A lone entrant walks into round two; a real tie waits to be played.
                'played' => $away === null,
                'winner' => $away === null ? $home : null,
                'seed' => $season->id * 1000 + 700 + $slot,
            ]);

            $slot++;
        }
    }

    /**
     * The entrants ('user' plus each rival team id) in a deterministic draw order,
     * seeded off the season so the same campaign always draws the same bracket.
     *
     * @return list<string>
     */
    private function shuffled(Season $season): array
    {
        $entrants = [CupTie::USER];
        foreach (Team::query()->where('is_youth', false)->orderBy('id')->pluck('id') as $id) {
            $entrants[] = (string) $id;
        }

        usort($entrants, fn (string $a, string $b) => strcmp(
            md5($season->id.':'.$a),
            md5($season->id.':'.$b),
        ));

        return $entrants;
    }
}
