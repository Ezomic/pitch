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
     * A fixed ten-outfielder formation spread across the pitch. Every player
     * shares the same attribute template, so the only thing that varies between
     * experiment arms is vision.
     *
     * @return array<int, Player>
     */
    public static function build(Attributes $template): array
    {
        $layout = [
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

        $players = [];
        foreach ($layout as $id => [$zone, $position]) {
            $players[$id] = new Player($id, 'P'.$id, $position, $zone, $template);
        }

        return $players;
    }

    public static function kickoffZone(): Zone
    {
        return new Zone(1, 1);
    }
}
