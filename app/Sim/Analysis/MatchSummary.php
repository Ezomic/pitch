<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;

/**
 * What a match produced, kept once it is over.
 *
 * A live match stores the curated commentary and the scoring slots and nothing
 * else: the event stream and the per-tick frames are thrown away as each slice
 * is played. That is fine for watching a match and useless afterwards, so
 * everything worth knowing about a match has to be tallied while it runs.
 *
 * A match is played a slice at a time, so a summary is built per slice and
 * merged into the one already stored. Merging is plain addition, which is what
 * makes the order slices arrive in irrelevant.
 */
final class MatchSummary
{
    /** The per-side counters a summary carries. */
    private const TEAM_KEYS = [
        'frames', 'shots', 'onTarget', 'goals', 'passes', 'passesCompleted',
        'crosses', 'fouls', 'corners', 'tackles', 'saves',
    ];

    /** The per-player counters a summary carries. */
    private const PLAYER_KEYS = [
        'goals', 'shots', 'passes', 'passesCompleted', 'crosses',
        'tackles', 'interceptions', 'clearances', 'fouls', 'saves',
    ];

    /**
     * Tally one slice of a match.
     *
     * @param  list<MatchEvent>  $events
     * @param  list<array{m: int, b: array{float, float}, c: int, s: int, p: list<array{float, float}>, j: bool, goal: int}>  $frames
     * @return array<string, mixed>
     */
    public function ofSlice(array $events, array $frames): array
    {
        $summary = self::empty();

        foreach ($frames as $frame) {
            $side = $frame['s'] === 0 ? 0 : 1;
            $summary['teams'][$side]['frames']++;
        }

        foreach ($events as $index => $event) {
            $side = $event->actorId >= 100 ? 1 : 0;
            $this->countTeam($summary, $side, $event);
            $this->countPlayer($summary, $event);

            if ($event->type->isShot()) {
                $summary['shots'][] = $this->shot($event, $side, $events, $index);
            }
        }

        return $summary;
    }

    /**
     * Add a slice onto the running summary. Counters add, the shot list appends.
     *
     * @param  array<string, mixed>|null  $running
     * @param  array<string, mixed>  $slice
     * @return array<string, mixed>
     */
    public function merge(?array $running, array $slice): array
    {
        $merged = $running ?? self::empty();

        foreach ([0, 1] as $side) {
            foreach (self::TEAM_KEYS as $key) {
                $merged['teams'][$side][$key] = ($merged['teams'][$side][$key] ?? 0) + ($slice['teams'][$side][$key] ?? 0);
            }
        }

        foreach ($slice['players'] as $id => $counters) {
            foreach (self::PLAYER_KEYS as $key) {
                $merged['players'][$id][$key] = ($merged['players'][$id][$key] ?? 0) + ($counters[$key] ?? 0);
            }
        }

        $merged['shots'] = [...$merged['shots'] ?? [], ...$slice['shots']];

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        $team = array_fill_keys(self::TEAM_KEYS, 0);

        return ['teams' => [0 => $team, 1 => $team], 'players' => [], 'shots' => []];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function countTeam(array &$summary, int $side, MatchEvent $event): void
    {
        $team = &$summary['teams'][$side];

        match (true) {
            $event->type->isShot() => [$team['shots']++, $event->success ? $team['goals']++ : null],
            $event->type === EventType::Pass => [$team['passes']++, $event->success ? $team['passesCompleted']++ : null],
            $event->type === EventType::Cross => $team['crosses']++,
            $event->type === EventType::Foul => $team['fouls']++,
            $event->type === EventType::Corner => $team['corners']++,
            $event->type === EventType::Save => $team['saves']++,
            $event->type->isTackle() => $team['tackles']++,
            default => null,
        };

        // On target is what the keeper had to deal with plus what went in, the
        // same reading MatchAnalyzer uses so the two never disagree.
        if ($event->type->isShot() && $event->success) {
            $team['onTarget']++;
        }

        if ($event->type === EventType::Save) {
            $summary['teams'][1 - $side]['onTarget']++;
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function countPlayer(array &$summary, MatchEvent $event): void
    {
        $id = $event->actorId;
        $summary['players'][$id] ??= array_fill_keys(self::PLAYER_KEYS, 0);
        $player = &$summary['players'][$id];

        match (true) {
            $event->type->isShot() => [$player['shots']++, $event->success ? $player['goals']++ : null],
            $event->type === EventType::Pass => [$player['passes']++, $event->success ? $player['passesCompleted']++ : null],
            $event->type === EventType::Cross => $player['crosses']++,
            $event->type->isTackle() => $player['tackles']++,
            $event->type === EventType::Interception => $player['interceptions']++,
            $event->type === EventType::Clearance => $player['clearances']++,
            $event->type === EventType::Foul => $player['fouls']++,
            $event->type === EventType::Save => $player['saves']++,
            default => null,
        };
    }

    /**
     * One shot, with where it was struck from and what became of it.
     *
     * The event says only whether it went in, so a miss is classified from what
     * the engine emitted next: a save, a block, or nothing at all for one that
     * missed the target.
     *
     * @param  list<MatchEvent>  $events
     * @return array{minute: int, side: int, actor: int, x: int, y: int, outcome: string}
     */
    private function shot(MatchEvent $event, int $side, array $events, int $index): array
    {
        $next = $events[$index + 1] ?? null;

        $outcome = match (true) {
            $event->success => 'goal',
            $next?->type === EventType::Save => 'saved',
            $next?->type === EventType::Block => 'blocked',
            default => 'off',
        };

        return [
            'minute' => $event->minute,
            'side' => $side,
            'actor' => $event->actorId,
            'x' => $event->from->x,
            'y' => $event->from->y,
            'outcome' => $outcome,
        ];
    }

    /**
     * The figures a manager reads, worked out from the raw counters.
     *
     * @param  array<string, mixed>  $summary
     * @return array{teams: list<array<string, int|float>>, shots: list<array<string, mixed>>}
     */
    public static function forDisplay(array $summary): array
    {
        $frames = max(1, ($summary['teams'][0]['frames'] ?? 0) + ($summary['teams'][1]['frames'] ?? 0));

        $teams = [];
        foreach ([0, 1] as $side) {
            $team = $summary['teams'][$side] ?? array_fill_keys(self::TEAM_KEYS, 0);
            $passes = max(1, $team['passes']);

            $teams[] = [
                'possession' => round($team['frames'] / $frames * 100, 1),
                'shots' => $team['shots'],
                'onTarget' => $team['onTarget'],
                'goals' => $team['goals'],
                'passes' => $team['passes'],
                'passAccuracy' => round($team['passesCompleted'] / $passes * 100, 1),
                'crosses' => $team['crosses'],
                'fouls' => $team['fouls'],
                'corners' => $team['corners'],
                'tackles' => $team['tackles'],
                'saves' => $team['saves'],
            ];
        }

        return ['teams' => $teams, 'shots' => array_values($summary['shots'] ?? [])];
    }
}
