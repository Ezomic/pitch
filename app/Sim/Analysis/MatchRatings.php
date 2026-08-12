<?php

declare(strict_types=1);

namespace App\Sim\Analysis;

/**
 * How each player did, out of 10.
 *
 * Read entirely off the contributions MatchSummary tallied while the match ran,
 * so the same match always rates the same and nothing is drawn.
 *
 * Everyone starts at par and moves from there on what they actually did. A
 * substitute is not punished for the time he was not on: he simply has less to
 * show, so a quiet cameo sits near par rather than being marked down for the
 * hour he watched. Passing accuracy only counts once a player has made enough
 * passes for it to mean anything, so one stray ball in five minutes does not
 * define a rating.
 */
final class MatchRatings
{
    /** A player who did nothing of note had an average game. */
    private const PAR = 6.0;

    private const GOAL = 1.3;

    private const SHOT = 0.12;

    private const CROSS = 0.06;

    private const TACKLE = 0.12;

    private const INTERCEPTION = 0.08;

    private const CLEARANCE = 0.05;

    private const SAVE = 0.22;

    private const FOUL = 0.15;

    /** Below this many passes, accuracy is too thin a sample to read anything into. */
    private const PASS_SAMPLE = 8;

    /** What a good pass completion looks like, and how far it can swing a rating. */
    private const PASS_PAR = 0.70;

    private const PASS_SWING = 2.0;

    /**
     * Rate everyone who appeared.
     *
     * @param  array<string, mixed>  $summary
     * @return array<array-key, float>
     */
    public function forMatch(array $summary): array
    {
        $ratings = [];

        foreach ($summary['players'] ?? [] as $who => $counters) {
            $ratings[$who] = $this->rate($counters);
        }

        return $ratings;
    }

    /**
     * The best rating of the match, and who had it. Null when nobody played.
     *
     * @param  array<string, mixed>  $summary
     * @return array{who: array-key, rating: float}|null
     */
    public function playerOfTheMatch(array $summary, ?callable $eligible = null): ?array
    {
        $best = null;

        foreach ($this->forMatch($summary) as $who => $rating) {
            if ($eligible !== null && ! $eligible($who)) {
                continue;
            }

            if ($best === null || $rating > $best['rating']) {
                $best = ['who' => $who, 'rating' => $rating];
            }
        }

        return $best;
    }

    /**
     * @param  array<string, int>  $c
     */
    private function rate(array $c): float
    {
        $rating = self::PAR
            + ($c['goals'] ?? 0) * self::GOAL
            + ($c['shots'] ?? 0) * self::SHOT
            + ($c['crosses'] ?? 0) * self::CROSS
            + ($c['tackles'] ?? 0) * self::TACKLE
            + ($c['interceptions'] ?? 0) * self::INTERCEPTION
            + ($c['clearances'] ?? 0) * self::CLEARANCE
            + ($c['saves'] ?? 0) * self::SAVE
            - ($c['fouls'] ?? 0) * self::FOUL;

        $passes = $c['passes'] ?? 0;
        if ($passes >= self::PASS_SAMPLE) {
            $accuracy = ($c['passesCompleted'] ?? 0) / $passes;
            $rating += ($accuracy - self::PASS_PAR) * self::PASS_SWING;
        }

        return round(max(1.0, min(10.0, $rating)), 1);
    }
}
