<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\MatchEvent;
use App\Sim\Squad\MatchCommentary;

/**
 * Turns the engine's raw per-tick output into the compact shapes the live 2D
 * replay consumes: the 22-player metadata (once), a downsampled position stream
 * per advance, and the key moments for the running feed. Mirrors PitchReplay,
 * but for slices of a match rather than a finished one.
 */
final class LivePitch
{
    /** Keep every Nth tick; the frontend interpolates between keyframes. */
    public const int STEP = 4;

    public function __construct(
        private readonly MatchCommentary $commentary = new MatchCommentary,
    ) {}

    /**
     * @param  array<int, string>  $homeNames  slot id => player name
     * @return list<array{s: int, slot: int, name: string|null, gk: bool}>
     */
    public function players(array $homeNames): array
    {
        $players = [];
        foreach ([0, 1] as $side) {
            foreach (range(0, 10) as $slot) {
                $players[] = [
                    's' => $side,
                    'slot' => $slot,
                    'name' => $side === 0 ? ($homeNames[$slot] ?? ($slot === 0 ? 'GK' : "Slot {$slot}")) : null,
                    'gk' => $slot === 0,
                ];
            }
        }

        return $players;
    }

    /**
     * The slice's position frames, downsampled on the global tick so the phase is
     * continuous across advances, with the carrier id mapped to its stream index.
     *
     * @param  list<array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>, j: bool, goal: int}>  $frames
     * @return list<array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>, j: bool, goal: int}>
     */
    public function frames(array $frames, int $startTick): array
    {
        $out = [];
        $cut = false;
        foreach ($frames as $i => $frame) {
            if ($frame['j']) {
                $cut = true; // the ball was placed somewhere in this downsample window
            }

            // Always keep the frame that shows the ball in the net, even off the
            // downsample cadence, so the goal is seen and announced on that frame.
            if (($startTick + $i) % self::STEP !== 0 && $frame['goal'] < 0) {
                continue;
            }

            $out[] = [
                'm' => $frame['m'],
                'b' => $frame['b'],
                'c' => $frame['c'] >= 0 ? $this->index($frame['c']) : -1,
                's' => $frame['s'],
                'p' => $frame['p'],
                'j' => $cut,
                'goal' => $frame['goal'],
            ];
            $cut = false;
        }

        return $out;
    }

    /**
     * The key moments in this slice, for the live commentary feed.
     *
     * @param  list<MatchEvent>  $events
     * @param  array<int, string>  $names
     * @return list<array{minute: int, side: int, kind: string, text: string}>
     */
    public function moments(array $events, array $names): array
    {
        $moments = [];
        foreach ($events as $index => $event) {
            $moment = $this->commentary->moment($event, $index, $names);
            if ($moment !== null) {
                $moments[] = [
                    'minute' => $moment->minute,
                    'side' => $event->actorId >= 100 ? 1 : 0,
                    'kind' => $moment->kind,
                    'text' => $moment->text,
                ];
            }
        }

        return $moments;
    }

    private function index(int $id): int
    {
        return intdiv($id, 100) * 11 + $id % 100;
    }
}
