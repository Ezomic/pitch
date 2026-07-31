<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Season;
use App\Models\Squad;
use App\Models\Team;

class Standings
{
    public function __construct(
        private readonly RateClubs $rateClubs = new RateClubs,
    ) {}

    /**
     * Fold the season's played fixtures into a ranked league table. The user is
     * one row, flagged, alongside every rival. Ordered by points, then goal
     * difference, then goals for.
     *
     * @return list<array<string, mixed>>
     */
    public function handle(Season $season, bool $youth = false): array
    {
        $rows = ['user' => $this->emptyRow($youth ? 'Your academy' : 'Your squad', true)];

        // The academy plays its own age-group league, which the senior star ratings
        // do not describe, so only senior tables carry them.
        $rated = $youth ? [] : $this->rateClubs->handle(Squad::query()->where('user_id', $season->user_id)->first());

        $teams = Team::query()->where('is_youth', $youth)->orderBy('id');

        if (! $youth) {
            $teams->where('division', $season->division);
        }

        foreach ($teams->get() as $team) {
            $rows[$team->id] = $this->emptyRow($team->name, false);
        }

        foreach ($rows as $key => $row) {
            $rows[$key]['worldStars'] = $rated[$key]['world'] ?? null;
            $rows[$key]['leagueStars'] = $rated[$key]['league'] ?? null;
        }

        foreach ($season->fixtures()->where('youth', $youth)->where('played', true)->get() as $fixture) {
            $homeKey = $fixture->home_team_id ?? 'user';
            $awayKey = $fixture->away_team_id ?? 'user';

            $this->credit($rows[$homeKey], (int) $fixture->home_goals, (int) $fixture->away_goals);
            $this->credit($rows[$awayKey], (int) $fixture->away_goals, (int) $fixture->home_goals);
        }

        $table = array_values($rows);

        usort($table, function (array $a, array $b): int {
            return [$b['points'], $b['goalDifference'], $b['goalsFor']]
                <=> [$a['points'], $a['goalDifference'], $a['goalsFor']];
        });

        return $table;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRow(string $name, bool $isUser): array
    {
        return [
            'name' => $name,
            'isUser' => $isUser,
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goalsFor' => 0,
            'goalsAgainst' => 0,
            'goalDifference' => 0,
            'points' => 0,
            'worldStars' => null,
            'leagueStars' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function credit(array &$row, int $goalsFor, int $goalsAgainst): void
    {
        $row['played']++;
        $row['goalsFor'] += $goalsFor;
        $row['goalsAgainst'] += $goalsAgainst;
        $row['goalDifference'] = $row['goalsFor'] - $row['goalsAgainst'];

        if ($goalsFor > $goalsAgainst) {
            $row['won']++;
            $row['points'] += 3;
        } elseif ($goalsFor === $goalsAgainst) {
            $row['drawn']++;
            $row['points']++;
        } else {
            $row['lost']++;
        }
    }
}
