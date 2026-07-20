<?php

declare(strict_types=1);

use App\Sim\Domain\Position;
use App\Sim\Engine\Formation;

/**
 * @return array{DF: int, MF: int, FW: int}
 */
function lineCounts(Formation $formation): array
{
    $counts = ['DF' => 0, 'MF' => 0, 'FW' => 0];
    foreach ($formation->layout as [, $position]) {
        $counts[$position->value]++;
    }

    return $counts;
}

it('derives position from depth', function () {
    expect(Formation::positionForDepth(0))->toBe(Position::Defender)
        ->and(Formation::positionForDepth(1))->toBe(Position::Defender)
        ->and(Formation::positionForDepth(2))->toBe(Position::Midfielder)
        ->and(Formation::positionForDepth(3))->toBe(Position::Midfielder)
        ->and(Formation::positionForDepth(4))->toBe(Position::Forward)
        ->and(Formation::positionForDepth(5))->toBe(Position::Forward);
});

it('ships the six recognisable presets keyed by id', function () {
    $ids = array_map(fn (Formation $f) => $f->id, array_values(Formation::all()));

    expect($ids)->toBe(['442', '433', '352', '4231', '532', '343']);
});

it('places players on distinct cells with a deep player starting the build-up', function () {
    foreach (Formation::all() as $formation) {
        $cells = [];
        foreach ($formation->layout as [$zone]) {
            $cells[] = $zone->x.'-'.$zone->y;
        }
        expect($cells)->toBe(array_unique($cells));

        [$kickoffZone, $kickoffPosition] = $formation->layout[$formation->kickoffSlot];
        expect($kickoffPosition)->toBe(Position::Defender)
            ->and($kickoffZone->x)->toBeLessThanOrEqual(1);
    }
});

it('matches the defender-midfielder-forward counts implied by each name', function () {
    expect(lineCounts(Formation::preset442()))->toBe(['DF' => 4, 'MF' => 4, 'FW' => 2])
        ->and(lineCounts(Formation::preset433()))->toBe(['DF' => 4, 'MF' => 3, 'FW' => 3])
        ->and(lineCounts(Formation::preset352()))->toBe(['DF' => 3, 'MF' => 5, 'FW' => 2])
        ->and(lineCounts(Formation::preset4231()))->toBe(['DF' => 4, 'MF' => 5, 'FW' => 1])
        ->and(lineCounts(Formation::preset532()))->toBe(['DF' => 5, 'MF' => 3, 'FW' => 2])
        ->and(lineCounts(Formation::preset343()))->toBe(['DF' => 3, 'MF' => 4, 'FW' => 3]);
});

it('builds a custom formation from a layout, deriving positions by depth', function () {
    $custom = Formation::custom([
        1 => [1, 2],
        2 => [3, 1],
        3 => [4, 2],
    ]);

    expect($custom->id)->toBe('custom')
        ->and($custom->layout[1][1])->toBe(Position::Defender)
        ->and($custom->layout[2][1])->toBe(Position::Midfielder)
        ->and($custom->layout[3][1])->toBe(Position::Forward)
        ->and($custom->kickoffSlot)->toBe(1);
});
