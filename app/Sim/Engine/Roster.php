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
     * The default (balanced) formation layout: slot id => [zone, nominal position].
     *
     * @return array<int, array{Zone, Position}>
     */
    public static function formation(): array
    {
        return Formation::balanced()->layout;
    }

    /**
     * The formation's slot ids in order (always 1..10).
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
    public static function build(Attributes $template, ?Formation $formation = null): array
    {
        $formation ??= Formation::balanced();

        $bySlot = [];
        foreach ($formation->slots() as $slot) {
            $bySlot[$slot] = $template;
        }

        return self::fromAttributes($bySlot, $formation);
    }

    /**
     * Build the roster from per-slot attributes, placing each slot's player at
     * that slot's formation zone.
     *
     * @param  array<int, Attributes>  $bySlot  slot id => attributes
     * @return array<int, Player>
     */
    public static function fromAttributes(array $bySlot, ?Formation $formation = null): array
    {
        $formation ??= Formation::balanced();

        $players = [];
        foreach ($formation->layout as $slot => [$zone, $position]) {
            $players[$slot] = new Player($slot, 'P'.$slot, $position, $zone, $bySlot[$slot]);
        }

        return $players;
    }

    public static function kickoffZone(): Zone
    {
        return Formation::balanced()->kickoffZone();
    }
}
