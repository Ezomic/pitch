<?php

declare(strict_types=1);

namespace App\Actions\Season;

use App\Models\Season;
use App\Models\Team;

class Standings
{
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

        foreach (Team::query()->where('is_youth', $youth)->orderBy('id')->get() as $team) {
            $rows[$team->id] = $this->emptyRow($team->name, false);
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
