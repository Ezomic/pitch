<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Domain\Zone;
use App\Sim\Engine\Formation;

/**
 * Lay both teams out on the 2D pitch at their formation positions, so the replay
 * can show the full field behind the ball. Coordinates are normalised to 0..1
 * and the away side is mirrored, exactly as MatchTimeline does for the ball, so
 * players and ball share one coordinate space. Formations hold the ten
 * outfielders; a goalkeeper is added per side at the back-centre.
 */
final class MatchLineups
{
    /**
     * @param  array<int, string>  $homeNames  slot id => player name
     * @return list<array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}>
     */
    public function build(Formation $home, Formation $away, array $homeNames): array
    {
        $players = [];

        foreach ($home->placements() as $slot => [$x, $y]) {
            $players[] = $this->player(0, $slot, $homeNames[$slot] ?? "Slot {$slot}", $x, $y, mirror: false);
        }
        $players[] = $this->keeper(0, $homeNames[0] ?? 'GK', mirror: false);

        foreach ($away->placements() as $slot => [$x, $y]) {
            $players[] = $this->player(1, $slot, null, $x, $y, mirror: true);
        }
        $players[] = $this->keeper(1, null, mirror: true);

        return $players;
    }

    /**
     * @return array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}
     */
    private function player(int $side, int $slot, ?string $name, int $zx, int $zy, bool $mirror): array
    {
        [$x, $y] = $this->point($zx / Zone::MAX_X, $zy / Zone::MAX_Y, $mirror);

        return ['s' => $side, 'slot' => $slot, 'name' => $name, 'x' => $x, 'y' => $y, 'gk' => false];
    }

    /**
     * @return array{s: int, slot: int, name: ?string, x: float, y: float, gk: bool}
     */
    private function keeper(int $side, ?string $name, bool $mirror): array
    {
        // The keeper sits on his own goal line, centred. Slot 0 keeps it out of
        // the 1..10 outfield range without clashing.
        [$x, $y] = $this->point(0.0, 0.5, $mirror);

        return ['s' => $side, 'slot' => 0, 'name' => $name, 'x' => $x, 'y' => $y, 'gk' => true];
    }

    /**
     * @return array{float, float}
     */
    private function point(float $x, float $y, bool $mirror): array
    {
        if ($mirror) {
            $x = 1 - $x;
        }

        return [round($x, 3), round($y, 3)];
    }
}
