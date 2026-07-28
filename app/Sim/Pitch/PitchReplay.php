<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\MatchEvent;
use App\Sim\Engine\MatchResult;
use App\Sim\Squad\MatchCommentary;
use App\Sim\Squad\MatchMoment;
use App\Sim\Squad\MatchReport;

/**
 * Turn a positional match into the report the app already renders: a scoreline
 * and stat line, a curated moments feed, and a compact position STREAM the 2D
 * replay plays back directly. The stream is the engine's real per-tick positions
 * (downsampled), so the replay shows the truth instead of positions invented
 * from the ball path.
 */
final class PitchReplay
{
    /** Keep every Nth tick; the frontend interpolates between keyframes. */
    private const int STEP = 4;

    public function __construct(
        private readonly MatchCommentary $commentary = new MatchCommentary,
    ) {}

    /**
     * @param  array<int, string>  $homeNames  slot id => player name
     */
    public function build(PitchResult $result, array $homeNames): MatchReport
    {
        $stats = new MatchResult($result->events);
        $names = $this->names($homeNames);

        return new MatchReport(
            homeGoals: $result->homeGoals,
            awayGoals: $result->awayGoals,
            shots: $stats->shots,
            passesCompleted: $stats->passesCompleted,
            progressivePasses: $stats->progressivePasses,
            moments: $this->feed($result->events, $names),
            stream: [
                'players' => $this->players($homeNames),
                'frames' => $this->frames($result->frames, $this->captions($result->events, $names)),
                'goals' => $this->goals($result->events),
            ],
        );
    }

    /**
     * When each goal was scored, so the replay scoreboard can tick up in time.
     *
     * @param  list<MatchEvent>  $events
     * @return list<array{m: int, s: int}>
     */
    private function goals(array $events): array
    {
        $goals = [];
        foreach ($events as $event) {
            if ($event->type->isShot() && $event->success) {
                $goals[] = ['m' => $event->minute, 's' => $event->actorId >= 100 ? 1 : 0];
            }
        }

        return $goals;
    }

    /**
     * @param  array<int, string>  $homeNames
     * @return array<int, string>
     */
    private function names(array $homeNames): array
    {
        $names = [];
        foreach (range(0, 10) as $slot) {
            $names[$slot] = $homeNames[$slot] ?? ($slot === 0 ? 'GK' : "Slot {$slot}");
            $names[100 + $slot] = 'Opposition';
        }

        return $names;
    }

    /**
     * @param  list<MatchEvent>  $events
     * @param  array<int, string>  $names
     * @return list<MatchMoment>
     */
    private function feed(array $events, array $names): array
    {
        $moments = [];
        foreach ($events as $index => $event) {
            $moment = $this->commentary->moment($event, $index, $names);
            if ($moment !== null) {
                $moments[] = $moment;
            }
        }

        return $moments;
    }

    /**
     * A caption per minute, carried forward, so the replay overlay always has a
     * line to show.
     *
     * @param  list<MatchEvent>  $events
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function captions(array $events, array $names): array
    {
        $byMinute = [];
        foreach ($events as $index => $event) {
            $actor = $names[$event->actorId] ?? null;
            $target = $event->targetId !== null ? ($names[$event->targetId] ?? null) : null;
            $goal = $event->type->isShot() && $event->success;
            $byMinute[$event->minute] = $this->commentary->label(
                $event->type, $event->success, $goal, $actor, $target, $this->commentary->key($event, $index),
            );
        }

        $filled = [];
        $current = '';
        for ($minute = 0; $minute < 90; $minute++) {
            if (isset($byMinute[$minute])) {
                $current = $byMinute[$minute];
            }
            $filled[$minute] = $current;
        }

        return $filled;
    }

    /**
     * @param  array<int, string>  $homeNames
     * @return list<array{s: int, slot: int, name: string|null, gk: bool}>
     */
    private function players(array $homeNames): array
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
     * @param  list<array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>, j: bool, goal: int}>  $frames
     * @param  array<int, string>  $captions
     * @return list<array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>, cap: string, j: bool, goal: int}>
     */
    private function frames(array $frames, array $captions): array
    {
        $out = [];
        $cut = false;
        foreach ($frames as $tick => $frame) {
            if ($frame['j']) {
                $cut = true;
            }

            // Always keep the frame that shows the ball in the net, even off the
            // downsample cadence, so the goal is seen on that frame.
            if ($tick % self::STEP !== 0 && $frame['goal'] < 0) {
                continue;
            }

            $out[] = [
                'm' => $frame['m'],
                'b' => $frame['b'],
                'c' => $frame['c'] >= 0 ? $this->index($frame['c']) : -1,
                's' => $frame['s'],
                'p' => $frame['p'],
                'cap' => $captions[$frame['m']] ?? '',
                'j' => $cut,
                'goal' => $frame['goal'],
            ];
            $cut = false;
        }

        return $out;
    }

    /** Map an engine player id (side*100 + slot) to its index in the 22-player stream order. */
    private function index(int $id): int
    {
        return intdiv($id, 100) * 11 + $id % 100;
    }
}
