<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\Position;
use App\Sim\Domain\Zone;

/**
 * A ten-outfielder shape: each slot placed at a zone on the 6x5 grid with a
 * position derived from its depth. Presets are the recognisable real formations;
 * a nominal position is inferred from the depth band so presets and custom
 * layouts share one representation.
 */
final readonly class Formation
{
    public const string CUSTOM_ID = 'custom';

    /**
     * @param  array<int, array{Zone, Position}>  $layout  slot id => [zone, position]
     */
    private function __construct(
        public string $id,
        public string $name,
        public array $layout,
        public int $kickoffSlot,
    ) {}

    public static function positionForDepth(int $x): Position
    {
        return match (true) {
            $x <= 1 => Position::Defender,
            $x <= 3 => Position::Midfielder,
            default => Position::Forward,
        };
    }

    public static function preset442(): self
    {
        return self::fromLines('442', '4-4-2', [[1, 4], [2, 4], [4, 2]]);
    }

    public static function preset433(): self
    {
        return self::fromLines('433', '4-3-3', [[1, 4], [2, 3], [4, 3]]);
    }

    public static function preset352(): self
    {
        return self::fromLines('352', '3-5-2', [[1, 3], [2, 5], [4, 2]]);
    }

    public static function preset4231(): self
    {
        return self::fromLines('4231', '4-2-3-1', [[1, 4], [2, 2], [3, 3], [4, 1]]);
    }

    public static function preset532(): self
    {
        return self::fromLines('532', '5-3-2', [[1, 5], [2, 3], [4, 2]]);
    }

    public static function preset343(): self
    {
        return self::fromLines('343', '3-4-3', [[1, 3], [2, 4], [4, 3]]);
    }

    /**
     * The default shape used whenever no formation is supplied. A balanced
     * 4-3-3, which also keeps the engine's null-formation path unchanged in
     * spirit (a solid back line, three in midfield, three up).
     */
    public static function balanced(): self
    {
        return self::preset433();
    }

    public static function defensive(): self
    {
        return self::preset532();
    }

    public static function attacking(): self
    {
        return self::preset343();
    }

    /**
     * Build a formation from a stored custom layout: slot => [x, y]. Positions
     * are derived from depth and the kickoff is the forward-most, most central
     * player.
     *
     * @param  array<int, array{int, int}>  $placements  slot id => [x, y]
     */
    public static function custom(array $placements): self
    {
        $layout = [];
        foreach ($placements as $slot => [$x, $y]) {
            $layout[$slot] = [new Zone($x, $y), self::positionForDepth($x)];
        }

        return new self('custom', 'Custom', $layout, self::kickoffFor($layout));
    }

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        $formations = [];
        foreach ([
            self::preset442(),
            self::preset433(),
            self::preset352(),
            self::preset4231(),
            self::preset532(),
            self::preset343(),
        ] as $formation) {
            $formations[$formation->id] = $formation;
        }

        return $formations;
    }

    public static function fromId(?string $id): self
    {
        return self::all()[$id] ?? self::default();
    }

    public static function default(): self
    {
        return self::balanced();
    }

    /**
     * @return list<int>
     */
    public function slots(): array
    {
        return array_keys($this->layout);
    }

    /**
     * The layout flattened to storable coordinates: slot id => [x, y]. The inverse
     * of Formation::custom(), used to seed a custom shape from a preset.
     *
     * @return array<int, array{int, int}>
     */
    public function placements(): array
    {
        $placements = [];
        foreach ($this->layout as $slot => [$zone]) {
            $placements[$slot] = [$zone->x, $zone->y];
        }

        return $placements;
    }

    public function kickoffZone(): Zone
    {
        return $this->layout[$this->kickoffSlot][0];
    }

    /**
     * Lay ten players out line by line, back to front. Each line is [x, count];
     * a line's players are spread symmetrically across the five lanes.
     *
     * @param  list<array{int, int}>  $lines
     */
    private static function fromLines(string $id, string $name, array $lines): self
    {
        $layout = [];
        $slot = 1;
        foreach ($lines as [$x, $count]) {
            foreach (self::lanes($count) as $y) {
                $layout[$slot] = [new Zone($x, $y), self::positionForDepth($x)];
                $slot++;
            }
        }

        return new self($id, $name, $layout, self::kickoffFor($layout));
    }

    /**
     * The lanes a line of $count players occupies, centred across the five lanes.
     *
     * @return list<int>
     */
    private static function lanes(int $count): array
    {
        return match ($count) {
            1 => [2],
            2 => [1, 3],
            3 => [1, 2, 3],
            4 => [0, 1, 3, 4],
            5 => [0, 1, 2, 3, 4],
            default => throw new \InvalidArgumentException("Unsupported line size: {$count}"),
        };
    }

    /**
     * The deepest, most central slot: who starts the build-up. Every attack
     * restarts from here, so possession begins at the back and has room to
     * progress through the thirds (where passing vision earns its keep).
     *
     * @param  array<int, array{Zone, Position}>  $layout
     */
    private static function kickoffFor(array $layout): int
    {
        $bestSlot = array_key_first($layout) ?? 1;
        $bestScore = null;

        foreach ($layout as $slot => [$zone]) {
            $score = [-$zone->x, -abs($zone->y - Zone::CENTRE_Y)];
            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
                $bestSlot = $slot;
            }
        }

        return $bestSlot;
    }
}
