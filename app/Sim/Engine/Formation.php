<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\Position;
use App\Sim\Domain\Zone;

/**
 * A ten-outfielder shape: each slot placed at a zone on the 6x3 grid with a
 * nominal position. The grid has three lanes, so a column holds at most three
 * players.
 */
final readonly class Formation
{
    /**
     * @param  array<int, array{Zone, Position}>  $layout  slot id => [zone, position]
     */
    private function __construct(
        public string $id,
        public string $name,
        public array $layout,
        public int $kickoffSlot,
    ) {}

    public static function balanced(): self
    {
        return new self('balanced', 'Balanced (3-3-1-3)', [
            1 => [new Zone(1, 0), Position::Defender],
            2 => [new Zone(1, 1), Position::Defender],
            3 => [new Zone(1, 2), Position::Defender],
            4 => [new Zone(2, 0), Position::Midfielder],
            5 => [new Zone(2, 1), Position::Midfielder],
            6 => [new Zone(2, 2), Position::Midfielder],
            7 => [new Zone(3, 1), Position::Midfielder],
            8 => [new Zone(4, 0), Position::Forward],
            9 => [new Zone(4, 1), Position::Forward],
            10 => [new Zone(4, 2), Position::Forward],
        ], 2);
    }

    public static function defensive(): self
    {
        return new self('defensive', 'Defensive (3-3-2-2)', [
            1 => [new Zone(1, 0), Position::Defender],
            2 => [new Zone(1, 1), Position::Defender],
            3 => [new Zone(1, 2), Position::Defender],
            4 => [new Zone(2, 0), Position::Midfielder],
            5 => [new Zone(2, 1), Position::Midfielder],
            6 => [new Zone(2, 2), Position::Midfielder],
            7 => [new Zone(3, 0), Position::Midfielder],
            8 => [new Zone(3, 2), Position::Midfielder],
            9 => [new Zone(4, 0), Position::Forward],
            10 => [new Zone(4, 2), Position::Forward],
        ], 2);
    }

    public static function attacking(): self
    {
        return new self('attacking', 'Attacking (2-3-2-3)', [
            1 => [new Zone(1, 0), Position::Defender],
            2 => [new Zone(1, 2), Position::Defender],
            3 => [new Zone(2, 0), Position::Midfielder],
            4 => [new Zone(2, 1), Position::Midfielder],
            5 => [new Zone(2, 2), Position::Midfielder],
            6 => [new Zone(3, 0), Position::Midfielder],
            7 => [new Zone(3, 2), Position::Midfielder],
            8 => [new Zone(4, 0), Position::Forward],
            9 => [new Zone(4, 1), Position::Forward],
            10 => [new Zone(4, 2), Position::Forward],
        ], 4);
    }

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        $formations = [];
        foreach ([self::balanced(), self::defensive(), self::attacking()] as $formation) {
            $formations[$formation->id] = $formation;
        }

        return $formations;
    }

    public static function fromId(?string $id): self
    {
        return self::all()[$id] ?? self::balanced();
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

    public function kickoffZone(): Zone
    {
        return $this->layout[$this->kickoffSlot][0];
    }
}
