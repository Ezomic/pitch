<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Player;
use App\Sim\Domain\Position;
use App\Sim\Domain\Zone;

final class Roster
{
    public const int KICKOFF_CARRIER_ID = 2;

    /**
     * The fixed ten-outfielder formation: slot id => [zone, nominal position].
     *
     * @return array<int, array{Zone, Position}>
     */
    public static function formation(): array
    {
        return [
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
        ];
    }

    /**
     * The formation's slot ids in order.
     *
     * @return list<int>
     */
    public static function slots(): array
    {
        return array_keys(self::formation());
    }

    /**
     * Build the roster from a single shared attribute template. Used by the
     * paired experiment, where only vision varies between arms.
     *
     * @return array<int, Player>
     */
    public static function build(Attributes $template): array
    {
        $bySlot = [];
        foreach (self::slots() as $slot) {
            $bySlot[$slot] = $template;
        }

        return self::fromAttributes($bySlot);
    }

    /**
     * Build the roster from per-slot attributes, placing each slot's player at
     * that slot's formation zone.
     *
     * @param  array<int, Attributes>  $bySlot  slot id => attributes
     * @return array<int, Player>
     */
    public static function fromAttributes(array $bySlot): array
    {
        $players = [];
        foreach (self::formation() as $slot => [$zone, $position]) {
            $players[$slot] = new Player($slot, 'P'.$slot, $position, $zone, $bySlot[$slot]);
        }

        return $players;
    }

    public static function kickoffZone(): Zone
    {
        return new Zone(1, 1);
    }
}
