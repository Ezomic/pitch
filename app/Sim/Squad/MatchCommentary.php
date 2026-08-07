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
    public function moment(MatchEvent $event, array $names): ?MatchMoment
    {
        $actor = $names[$event->actorId] ?? "Player {$event->actorId}";
        $target = $event->targetId !== null ? ($names[$event->targetId] ?? "Player {$event->targetId}") : null;
        $key = $this->key($event);

        return match ($event->type) {
            EventType::Header, EventType::Shot => $event->success
                ? new MatchMoment($event->minute, 'goal', $this->fill($this->goalPool($event->type), $actor, $target, $key))
                : null, // the save or block that follows tells the story
            EventType::Save => new MatchMoment($event->minute, 'save', $this->pick(self::SAVES, $key)),
            EventType::Block => new MatchMoment($event->minute, 'save', $this->pick(self::BLOCKS, $key)),
            EventType::Foul => new MatchMoment($event->minute, 'setpiece', $this->pick(self::FOULS, $key)),
            EventType::Corner => new MatchMoment($event->minute, 'setpiece', $this->pick(self::CORNERS, $key)),
            EventType::Penalty => new MatchMoment($event->minute, 'setpiece', 'Penalty awarded!'),
            EventType::Cross => $event->success
                ? new MatchMoment($event->minute, 'chance', $this->fill(self::CROSSES, $actor, $target, $key, self::CROSSES_SOLO))
                : null,
            EventType::Tackle, EventType::SlideTackle, EventType::Interception, EventType::Clearance => $this->defensiveMoment($event, $key),
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

        // Same label on both ends (the unnamed opposition) would read
        // "Opposition → Opposition"; drop the duplicated receiver.
        if ($actor !== null && $actor === $target) {
            $target = null;
        }

        return match ($type) {
            EventType::Header => $ok ? 'Header on goal' : 'Header off target',
            EventType::Shot => $ok ? 'Shot on goal' : 'Shot saved',
            EventType::Save => 'Saved!',
            EventType::Block => 'Blocked!',
            EventType::Interception => 'Intercepted',
            EventType::Tackle => 'Tackled',
            EventType::SlideTackle => 'Slide tackle!',
            EventType::Clearance => 'Cleared',
            EventType::Foul => 'Free-kick won',
            EventType::Corner => 'Corner',
            EventType::ThrowIn => 'Throw-in',
            EventType::GoalKick => 'Goal kick',
            EventType::Penalty => 'Penalty!',
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
            return new MatchMoment($event->minute, 'chance', $this->fill(self::THROUGH_BALLS, $actor, $target, $key, self::THROUGH_BALLS_SOLO));
        }

        if ($key % 2 === 0) {
            return new MatchMoment($event->minute, 'chance', $this->fill(self::FINAL_THIRD, $actor, $target, $key, self::FINAL_THIRD_SOLO));
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
            EventType::SlideTackle => self::SLIDE_TACKLES,
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
     * @param  list<string>  $soloPool  subjectless variants used when actor === target
     */
    private function fill(array $pool, string $actor, ?string $target, int $key, array $soloPool = []): string
    {
        if ($target !== null && $actor === $target && $soloPool !== []) {
            return str_replace('{actor}', $actor, $this->pick($soloPool, $key));
        }

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

    /**
     * The phrasing key for an event, derived only from the event itself.
     *
     * This used to fold in the event's position within the batch it happened to
     * be generated in. A live match is simulated a slice at a time and the
     * client chooses the slice size, so the same event could come out with a
     * different key depending on where a chunk boundary fell. Because the gates
     * above sample on that key, an event could be narrated in one run of the
     * same match and silent in another.
     */
    public function key(MatchEvent $event): int
    {
        return $this->keyFor($event->minute, $event->actorId, $this->shape($event));
    }

    public function keyFor(int $minute, int $actorId, int $index): int
    {
        return ($minute * 131 + $actorId * 17 + $index * 7) & 0x7FFFFFFF;
    }

    /**
     * What separates two events that share a minute and an actor, so a pass and
     * the shot that follows it do not read identically. Every part of it is
     * intrinsic to what happened: who it was aimed at, what kind of action it
     * was, where it started and ended, and whether it came off.
     *
     * The type is hashed by name rather than by its position in the enum, so
     * reordering the cases cannot quietly rewrite every line in the game.
     */
    private function shape(MatchEvent $event): int
    {
        $to = $event->to;

        return (int) (crc32($event->type->value) % 101) * 13
            + ($event->targetId ?? 0) * 29
            + ($event->success ? 5 : 0)
            + $event->from->x * 3 + $event->from->y * 7
            + ($to->x ?? 0) * 11 + ($to->y ?? 0) * 2;
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

    // When actor and target are the same label (the unnamed opposition passing
    // among themselves), the {target} templates would read "Opposition ...
    // Opposition"; these subjectless variants are used instead.
    /** @var list<string> */
    private const CROSSES_SOLO = [
        '{actor} whip a cross into the box.',
        '{actor} swing one into the area.',
        '{actor} clip a ball to the far post.',
        '{actor} float a cross into the box.',
    ];

    /** @var list<string> */
    private const THROUGH_BALLS = [
        '{actor} slips {target} in behind.',
        '{actor} splits the defence for {target}.',
        '{actor} releases {target} through the middle.',
    ];

    /** @var list<string> */
    private const THROUGH_BALLS_SOLO = [
        '{actor} slip a ball in behind.',
        '{actor} split the defence.',
        '{actor} work it through the middle.',
    ];

    /** @var list<string> */
    private const FINAL_THIRD = [
        '{actor} threads it through to {target}.',
        '{actor} picks out {target} between the lines.',
        '{actor} feeds {target} on the edge of the box.',
        '{actor} works it forward to {target}.',
    ];

    /** @var list<string> */
    private const FINAL_THIRD_SOLO = [
        '{actor} thread it into the box.',
        '{actor} work an opening between the lines.',
        '{actor} knock it to the edge of the box.',
        '{actor} work it into the final third.',
    ];

    /** @var list<string> */
    private const SLIDE_TACKLES = [
        'A full-blooded sliding challenge takes the ball.',
        'He goes to ground and gets every bit of the ball.',
        'A perfectly timed slide, and the danger is gone.',
        'He commits, slides in, and comes away with it.',
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
