<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Models\LiveMatch;
use App\Models\Player;

/**
 * Bring a fresh player on for one already on the pitch. The incoming player
 * takes the same slot and position; only their attributes and name change, so
 * the swap is a small edit of the persisted state and needs no re-simulation.
 *
 * The change is recorded as a moment, so the player who came off stays in the
 * match record rather than being quietly overwritten by whoever replaced them.
 */
class Substitute
{
    public function handle(LiveMatch $match, int $outSlot, Player $in): void
    {
        if (! $this->allowed($match, $outSlot, $in)) {
            return;
        }

        $state = $match->pitch_state;
        foreach ($state['players'] as &$player) {
            if ((int) $player['id'] === $outSlot && (int) $player['side'] === 0) {
                $player['attr'] = [$in->vision, $in->passing, $in->dribbling, $in->finishing, $in->tackling, $in->pace];
            }
        }
        unset($player);

        $off = $this->nameInSlot($match, $outSlot);

        $players = $match->players;
        foreach ($players as &$meta) {
            if ((int) $meta['s'] === 0 && (int) $meta['slot'] === $outSlot) {
                $meta['pid'] = $in->id;
                $meta['name'] = $in->name;
            }
        }
        unset($meta);

        $match->update([
            'pitch_state' => $state,
            'players' => $players,
            'moments' => [...$match->moments, $this->moment($match, $off, $in->name)],
            // Recorded with the tick it happened on, so a replay can make the
            // same change at the same moment and come out identical.
            'interventions' => [...$match->interventions ?? [], [
                'tick' => $match->current_tick,
                'type' => 'sub',
                'slot' => $outSlot,
                'pid' => $in->id,
                'name' => $in->name,
                'attr' => [$in->vision, $in->passing, $in->dribbling, $in->finishing, $in->tackling, $in->pace],
                'off' => $off,
            ]],
            'subs_remaining' => $match->subs_remaining - 1,
        ]);
    }

    /** Whether the swap is one the match can actually make. */
    public function allowed(LiveMatch $match, int $outSlot, Player $in): bool
    {
        return $match->subs_remaining > 0
            && $match->status === LiveMatch::LIVE
            && $this->nameInSlot($match, $outSlot) !== null
            && ! $this->onPitch($match, $in);
    }

    /** Whether this player is already in one of the eleven slots. */
    public function onPitch(LiveMatch $match, Player $in): bool
    {
        foreach ($match->players as $meta) {
            if ((int) $meta['s'] === 0 && ($meta['pid'] ?? null) === $in->id) {
                return true;
            }
        }

        return false;
    }

    private function nameInSlot(LiveMatch $match, int $slot): ?string
    {
        foreach ($match->players as $meta) {
            if ((int) $meta['s'] === 0 && (int) $meta['slot'] === $slot) {
                return $meta['name'] ?? null;
            }
        }

        return null;
    }

    /**
     * @return array{minute: int, side: int, kind: string, text: string, why: null}
     */
    private function moment(LiveMatch $match, ?string $off, string $on): array
    {
        return [
            'minute' => (int) min(90, $match->current_tick / max(1, $match->total_ticks) * 90),
            'side' => 0,
            'kind' => 'sub',
            'text' => sprintf('Substitution: %s on for %s.', $on, $off ?? 'a team-mate'),
            'why' => null,
        ];
    }
}
