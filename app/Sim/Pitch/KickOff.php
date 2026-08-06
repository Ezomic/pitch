<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\Attributes;
use App\Sim\Domain\Player;
use App\Sim\Domain\Position;
use App\Sim\Domain\Zone;

/**
 * Setting the pitch: turning two formations into 22 placed players, and putting
 * the ball on the centre spot for a restart with everyone where the laws say
 * they should be.
 *
 * No random draw is taken here. A kickoff is fully determined by the two sides
 * and which of them is taking it, which is why the engine can hand a restart
 * back mid-match without disturbing the stream.
 */
final class KickOff
{
    // The centre circle in normalised space. The pitch renders 3:2, so the circle
    // is an ellipse in 0..1 coordinates: wider across the goals than across the
    // width. No defender may stand inside it at a restart.
    public const float CIRCLE_RX = 0.087;

    public const float CIRCLE_RY = 0.13;

    /**
     * @param  array<int, Player>  $home
     * @param  array<int, Player>  $away
     * @return array<int, PlayerState>
     */
    public function buildStates(array $home, array $away): array
    {
        $states = [];

        foreach ([[0, $home], [1, $away]] as [$side, $players]) {
            /** @var array<int, Player> $players */
            foreach ($players as $slot => $player) {
                $anchor = $this->anchor($side, $player->zone->x / Zone::MAX_X, $player->zone->y / Zone::MAX_Y);
                $id = PlayerState::id($side, $slot);
                $states[$id] = new PlayerState($id, $side, $slot, $player->position, $anchor, $player->attributes);
            }

            // Every team needs a keeper on its own line; a solid default until the
            // goalkeeper becomes a positional lever in a later stage.
            $keeperX = $side === 0 ? 0.03 : 0.97;
            $keeperId = PlayerState::id($side, 0);
            $states[$keeperId] = new PlayerState(
                $keeperId, $side, 0, Position::Goalkeeper,
                new Vec2($keeperX, 0.5), new Attributes(45, 45, 45, 45, 62, 45),
            );
        }

        return $states;
    }

    /** Place a side-relative anchor (advanced = toward the opponent goal) in pitch space. */
    public function anchor(int $side, float $advance, float $width): Vec2
    {
        // Home attacks toward x=1, so a more advanced zone sits further right; the
        // away side is mirrored. Deep players stay home; the forward line sits
        // higher so attacks have bodies near the opponent box to build onto.
        $x = $side === 0 ? 0.06 + $advance * 0.5 : 0.94 - $advance * 0.5;

        return new Vec2($x, 0.08 + $width * 0.84);
    }

    /**
     * @param  array<int, PlayerState>  $states
     */
    public function restart(array $states, int $side): PitchState
    {
        $state = new PitchState($states, new Vec2(0.5, 0.5), PitchState::NO_CARRIER);
        $state->possessing = $side;
        $state->teleported = true; // the ball is placed on the centre spot

        // No defender may stand inside the centre circle at the restart; push any
        // that do out to its edge.
        $defending = 1 - $side;
        foreach ($state->players as $player) {
            if ($player->side === $defending && ! $player->isGoalkeeper()) {
                $this->pushOutsideCentreCircle($player, $defending);
            }
        }

        // The kicking-off side taps off: a central player on the spot, with a
        // second dropping just behind to receive.
        [$passer, $receiver] = $this->kickoffPair($state, $side);
        if ($passer !== null) {
            $passer->pos = new Vec2(0.5, 0.5);
            $state->carrierId = $passer->id;
            $state->ball = $passer->pos;
        }
        if ($receiver !== null) {
            $receiver->pos = new Vec2($side === 0 ? 0.46 : 0.54, 0.5);
        }

        return $state;
    }

    /**
     * Move a defender that has strayed inside the centre circle radially out to
     * its edge; a player exactly on the spot retreats toward its own goal.
     */
    private function pushOutsideCentreCircle(PlayerState $player, int $defendingSide): void
    {
        $dx = $player->pos->x - 0.5;
        $dy = $player->pos->y - 0.5;
        $norm = ($dx / self::CIRCLE_RX) ** 2 + ($dy / self::CIRCLE_RY) ** 2;

        if ($norm >= 1.0) {
            return;
        }

        if ($norm <= 1e-9) {
            $dx = $defendingSide === 0 ? -self::CIRCLE_RX : self::CIRCLE_RX;
            $dy = 0.0;
            $norm = 1.0;
        }

        // A hair beyond the edge so the player is unambiguously outside the circle.
        $scale = 1.02 / sqrt($norm);
        $player->pos = (new Vec2(0.5 + $dx * $scale, 0.5 + $dy * $scale))->clampToPitch();
    }

    /**
     * The two most central outfielders of a side, by formation anchor: the passer
     * who taps off and the team-mate dropping in to receive.
     *
     * @return array{?PlayerState, ?PlayerState}
     */
    private function kickoffPair(PitchState $state, int $side): array
    {
        $centre = new Vec2(0.5, 0.5);
        $outfield = [];
        foreach ($state->players as $player) {
            if ($player->side === $side && ! $player->isGoalkeeper()) {
                $outfield[] = $player;
            }
        }

        usort(
            $outfield,
            fn (PlayerState $a, PlayerState $b): int => $a->anchor->distanceTo($centre) <=> $b->anchor->distanceTo($centre),
        );

        return [$outfield[0] ?? null, $outfield[1] ?? null];
    }
}
