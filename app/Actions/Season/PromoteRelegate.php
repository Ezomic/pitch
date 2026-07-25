<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Season;
use App\Models\Squad;
use App\Models\Team;

/**
 * Settle a finished campaign's league position into a move between divisions:
 * win your division and you go up, finish bottom and you go down. The club's tier
 * is stored on the squad, so it drives which teams next season is scheduled
 * against. Only the user's club moves; the AI sides hold their tier.
 */
class PromoteRelegate
{
    public function __construct(
        private readonly Standings $standings = new Standings,
    ) {}

    /**
     * @return 'promoted'|'relegated'|'stayed'
     */
    public function handle(Squad $squad, Season $season): string
    {
        $table = $this->standings->handle($season);
        $teams = count($table);

        $position = $teams;
        foreach ($table as $index => $row) {
            if ($row['isUser'] === true) {
                $position = $index + 1;
                break;
            }
        }

        $division = $season->division;

        if ($position === 1 && $division > Squad::TOP_DIVISION && $this->divisionHasTeams($division - 1)) {
            $squad->forceFill(['division' => $division - 1])->save();

            return 'promoted';
        }

        if ($position === $teams && $division < Squad::BOTTOM_DIVISION && $this->divisionHasTeams($division + 1)) {
            $squad->forceFill(['division' => $division + 1])->save();

            return 'relegated';
        }

        return 'stayed';
    }

    private function divisionHasTeams(int $division): bool
    {
        return Team::query()->where('is_youth', false)->where('division', $division)->exists();
    }
}
