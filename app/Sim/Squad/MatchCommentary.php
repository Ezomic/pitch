<?php

declare(strict_types=1);

namespace App\Sim\Squad;

use App\Sim\Domain\EventType;
use App\Sim\Domain\MatchEvent;

/**
 * The words for a match: deterministic pools of phrasings for every kind of
 * event, so the feed and the 2D replay read varied without ever losing
 * reproducibility (the same seed always picks the same line). One source of
 * commentary, shared by the highlights feed and the pitch captions.
 */
final class MatchCommentary
{
    /**
     * A curated highlight line for the feed, or null when the event is too
     * routine to mention. Keeps the feed reading like highlights rather than a
     * full commentary of every touch.
     *
     * @param  array<int, string>  $names  slot id => player name
     */
    public function moment(MatchEvent $event, int $index, array $names): ?MatchMoment
    {
        $actor = $names[$event->actorId] ?? "Player {$event->actorId}";
        $target = $event->targetId !== null ? ($names[$event->targetId] ?? "Player {$event->targetId}") : null;
        $key = $this->key($event, $index);

        return match ($event->type) {
            EventType::Header, EventType::Shot => $event->success
                ? new MatchMoment($event->minute, 'goal', $this->fill($this->goalPool($event->type), $actor, $target, $key))
                : null, // the save or block that follows tells the story
            EventType::Save => new MatchMoment($event->minute, 'save', $this->pick(self::SAVES, $key)),
            EventType::Block => new MatchMoment($event->minute, 'save', $this->pick(self::BLOCKS, $key)),
            EventType::Foul => new MatchMoment($event->minute, 'setpiece', $this->pick(self::FOULS, $key)),
            EventType::Corner => new MatchMoment($event->minute, 'setpiece', $this->pick(self::CORNERS, $key)),
            EventType::Cross => $event->success
                ? new MatchMoment($event->minute, 'chance', $this->fill(self::CROSSES, $actor, $target, $key))
                : null,
            EventType::Tackle, EventType::Interception, EventType::Clearance => $this->defensiveMoment($event, $key),
            EventType::Pass => $this->passMoment($event, $actor, $target, $key),
            default => null,
        };
    }

    /** A short caption for a single 2D-replay frame. */
    public function label(EventType $type, bool $ok, bool $goal, ?string $actor, ?string $target, int $key): string
    {
        if ($goal) {
            return 'GOAL';
        }

        $who = $actor ?? 'The ball';

        return match ($type) {
            EventType::Header => $ok ? 'Header on goal' : 'Header off target',
            EventType::Shot => $ok ? 'Shot on goal' : 'Shot saved',
            EventType::Save => 'Saved!',
            EventType::Block => 'Blocked!',
            EventType::Interception => 'Intercepted',
            EventType::Tackle => 'Tackled',
            EventType::Clearance => 'Cleared',
            EventType::Foul => 'Free-kick won',
            EventType::Corner => 'Corner',
            EventType::Cross => $target !== null ? "{$who} crosses to {$target}" : "{$who} crosses",
            EventType::Dribble => $ok ? "{$who} drives forward" : "{$who} dispossessed",
            default => $target !== null
                ? ($ok ? "{$who} → {$target}" : "{$who} → {$target}, cut out")
                : "{$who} on the ball",
        };
    }

    private function passMoment(MatchEvent $event, string $actor, ?string $target, int $key): ?MatchMoment
    {
        if (! $event->isProgressivePass() || $event->to === null || $event->to->x < 4) {
            return null;
        }

        // A ball in behind is always worth a line; a final-third entry is sampled
        // so the feed does not list every one.
        if ($event->to->x >= 5) {
            return new MatchMoment($event->minute, 'chance', $this->fill(self::THROUGH_BALLS, $actor, $target, $key));
        }

        if ($key % 2 === 0) {
            return new MatchMoment($event->minute, 'chance', $this->fill(self::FINAL_THIRD, $actor, $target, $key));
        }

        return null;
    }

    private function defensiveMoment(MatchEvent $event, int $key): ?MatchMoment
    {
        // Only stops in and around the box, and only some of them, so routine
        // clearances do not drown the feed.
        if ($event->from->x < 4 || $key % 5 >= 2) {
            return null;
        }

        $pool = match ($event->type) {
            EventType::Tackle => self::TACKLES,
            EventType::Interception => self::INTERCEPTIONS,
            default => self::CLEARANCES,
        };

        return new MatchMoment($event->minute, 'turnover', $this->pick($pool, $key));
    }

    /**
     * @return list<string>
     */
    private function goalPool(EventType $type): array
    {
        return $type === EventType::Header ? self::HEADER_GOALS : self::SHOT_GOALS;
    }

    /**
     * @param  list<string>  $pool
     */
    private function fill(array $pool, string $actor, ?string $target, int $key): string
    {
        $text = $this->pick($pool, $key);

        return str_replace(['{actor}', '{target}'], [$actor, $target ?? 'a team-mate'], $text);
    }

    /**
     * @param  list<string>  $pool
     */
    private function pick(array $pool, int $key): string
    {
        return $pool[$key % count($pool)];
    }

    public function key(MatchEvent $event, int $index): int
    {
        return ($event->minute * 131 + $event->actorId * 17 + $index * 7) & 0x7FFFFFFF;
    }

    /** @var list<string> */
    private const SHOT_GOALS = [
        'GOAL! {actor} finds the bottom corner.',
        'GOAL! {actor} rifles it past the keeper.',
        'GOAL! {actor} makes no mistake from range.',
        'GOAL! {actor} sweeps it home.',
        'GOAL! {actor} buries the chance.',
    ];

    /** @var list<string> */
    private const HEADER_GOALS = [
        'GOAL! {actor} rises highest and heads it home.',
        'GOAL! {actor} powers a header past the keeper.',
        'GOAL! {actor} nods it in at the far post.',
    ];

    /** @var list<string> */
    private const SAVES = [
        'A fine save keeps it out.',
        'The keeper gets down well to deny the shot.',
        'Superb reflexes from the goalkeeper.',
        'The keeper stands tall and smothers it.',
    ];

    /** @var list<string> */
    private const BLOCKS = [
        'A defender throws himself in front of it.',
        'Blocked bravely on the line.',
        'The block deflects it to safety.',
    ];

    /** @var list<string> */
    private const CROSSES = [
        '{actor} whips a cross towards {target}.',
        '{actor} swings one in for {target}.',
        '{actor} clips a ball to the far post for {target}.',
        '{actor} floats a cross onto the head of {target}.',
    ];

    /** @var list<string> */
    private const THROUGH_BALLS = [
        '{actor} slips {target} in behind.',
        '{actor} splits the defence for {target}.',
        '{actor} releases {target} through the middle.',
    ];

    /** @var list<string> */
    private const FINAL_THIRD = [
        '{actor} threads it through to {target}.',
        '{actor} picks out {target} between the lines.',
        '{actor} feeds {target} on the edge of the box.',
        '{actor} works it forward to {target}.',
    ];

    /** @var list<string> */
    private const TACKLES = [
        'A crunching tackle halts the attack.',
        'A strong challenge wins it back.',
        'Dispossessed by a fine tackle.',
    ];

    /** @var list<string> */
    private const INTERCEPTIONS = [
        'The pass is read and cut out.',
        'Intercepted before it reaches the striker.',
        'A defender steps across to snuff it out.',
    ];

    /** @var list<string> */
    private const CLEARANCES = [
        'The defence hacks it clear.',
        'Hooked away by the back line.',
        'Scrambled behind under pressure.',
    ];

    /** @var list<string> */
    private const FOULS = [
        'A cynical foul concedes a free-kick in a dangerous area.',
        'The referee spots a foul and it is a free-kick.',
        'A late challenge gives away a free-kick.',
    ];

    /** @var list<string> */
    private const CORNERS = [
        'The pressure tells and it is a corner.',
        'Deflected behind for a corner.',
        'A corner is won on the right.',
    ];
}
