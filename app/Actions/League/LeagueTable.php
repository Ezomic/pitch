<?php

declare(strict_types=1);

namespace App\Actions\League;

use App\Models\Career;
use App\Models\Season;
use App\Models\Team;

/**
 * The one table a league is read off.
 *
 * A solo table has a row for the manager and a row for each rival. A league has
 * neither: every row is a real club, and the only thing that distinguishes them
 * is whether a manager runs it or the AI does.
 */
class LeagueTable
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Career $career, Season $season): array
    {
        /** @var array<int, string> $managers */
        $managers = [];
        foreach ($career->memberships()->with('user')->whereNotNull('team_id')->get() as $seat) {
            if ($seat->team_id !== null) {
                $managers[$seat->team_id] = $seat->user->name;
            }
        }

        $rows = [];
        foreach (Team::query()->where('is_youth', false)->orderBy('id')->get() as $team) {
            $rows[$team->id] = $this->emptyRow($team, $managers[$team->id] ?? null);
        }

        foreach ($season->fixtures()->where('youth', false)->where('played', true)->get() as $fixture) {
            if (! isset($rows[$fixture->home_team_id]) || ! isset($rows[$fixture->away_team_id])) {
                continue;
            }

            $this->credit($rows[$fixture->home_team_id], (int) $fixture->home_goals, (int) $fixture->away_goals);
            $this->credit($rows[$fixture->away_team_id], (int) $fixture->away_goals, (int) $fixture->home_goals);
        }

        $table = array_values($rows);

        usort($table, fn (array $a, array $b): int => [$b['points'], $b['goalDifference'], $b['goalsFor'], $a['name']]
            <=> [$a['points'], $a['goalDifference'], $a['goalsFor'], $b['name']]);

        return $table;
    }

    /**
     * A club's overall standing in the world, used to tell a result apart from
     * an upset. The flat profile the sim reads, averaged.
     */
    public static function strength(Team $team): int
    {
        $attributes = [$team->vision, $team->passing, $team->dribbling, $team->finishing, $team->tackling, $team->pace];

        return (int) round(array_sum($attributes) / count($attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRow(Team $team, ?string $manager): array
    {
        return [
            'teamId' => $team->id,
            'name' => $team->name,
            'manager' => $manager,
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
