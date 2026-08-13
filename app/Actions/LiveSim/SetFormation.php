<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Models\LiveMatch;
use App\Sim\Domain\Zone;
use App\Sim\Engine\Formation;
use App\Sim\Pitch\KickOff;

/**
 * Change the manager's shape during a match.
 *
 * A player's anchor and nominal position are readonly on PlayerState, so a new
 * shape cannot be assigned onto the players in place. The state is rebuilt from
 * its snapshot with the new anchors written in, which is the same way a
 * substitution swaps a player's attributes.
 *
 * Only the anchors move. Nobody is teleported: the players walk to their new
 * stations over the following ticks, because an anchor is where a player wants
 * to be rather than where he is.
 */
class SetFormation
{
    public function __construct(
        private readonly KickOff $kickOff = new KickOff,
    ) {}

    public function handle(LiveMatch $match, Formation $formation): void
    {
        if ($match->status !== LiveMatch::LIVE) {
            return;
        }

        $state = $match->pitch_state;
        $anchors = $this->anchors($formation);

        foreach ($state['players'] as &$player) {
            if ((int) $player['side'] !== 0) {
                continue;
            }

            $slot = (int) $player['slot'];

            if (! isset($anchors[$slot])) {
                continue; // the keeper keeps his line whatever the shape
            }

            [$anchor, $position] = $anchors[$slot];
            $player['anchor'] = $anchor;
            $player['pos'] = $position;
        }
        unset($player);

        $match->update([
            'pitch_state' => $state,
            // Recorded with the tick it happened on, so a replay makes the same
            // change at the same moment and comes out identical.
            'interventions' => [...$match->interventions ?? [], [
                'tick' => $match->current_tick,
                'type' => 'formation',
                'value' => $formation->id,
            ]],
        ]);
    }

    /**
     * Each outfield slot's new anchor in pitch space, and the position that
     * depth implies, in the shape the snapshot stores them.
     *
     * @return array<int, array{array{float, float}, string}>
     */
    private function anchors(Formation $formation): array
    {
        $anchors = [];

        foreach ($formation->layout as $slot => [$zone, $position]) {
            $anchors[$slot] = [
                $this->kickOff->anchor(0, $zone->x / Zone::MAX_X, $zone->y / Zone::MAX_Y)->pair(),
                $position->value,
            ];
        }

        return $anchors;
    }
}
