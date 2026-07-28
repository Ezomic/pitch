<?php

declare(strict_types=1);

namespace App\Sim\Pitch;

use App\Sim\Domain\EventType;

/**
 * The live state of a positional match: every player, the ball, who has it, and
 * the ball's flight when it is travelling (a pass or a shot in the air). Mutable;
 * the engine advances it one tick at a time.
 */
final class PitchState
{
    /** No carrier: the ball is in flight or loose. */
    public const int NO_CARRIER = -1;

    public int $possessing = 0;

    public float $clock = 0.0;

    // Ball flight. When carrierId is NO_CARRIER the ball travels from its current
    // position toward $ballTarget at $ballSpeed, bound for $ballTo (a receiver id,
    // or NO_CARRIER for a shot at goal).
    public ?Vec2 $ballTarget = null;

    public int $ballTo = self::NO_CARRIER;

    public float $ballSpeed = 0.0;

    public string $ballKind = 'idle';

    /** True while a struck shot is flying goalward and will beat the keeper. */
    public bool $ballGoal = false;

    /** Ticks the current carrier has held the ball, so decisions pace out. */
    public int $holdTicks = 0;

    /** Ticks left of a dead-ball pause after a set piece is awarded. */
    public int $deadBall = 0;

    /**
     * True on the tick the ball was placed by hand (a kickoff or a set-piece
     * restart) rather than travelling there. Transient (reset each tick, not
     * persisted); the replay uses it to snap the ball instead of gliding it, so a
     * placed ball does not drift untouched across the pitch.
     */
    public bool $teleported = false;

    /**
     * The side that just scored on this tick (0 or 1), or -1. Transient (reset
     * each tick, not persisted); it marks the frame that shows the ball in the
     * net so the replay can hold on it and announce the goal at the right moment.
     */
    public int $justScored = -1;

    /**
     * A kickoff owed from a goal on the previous tick: the scoring tick shows the
     * ball in the net, then this restarts play. Persisted, so a goal on a slice
     * boundary still restarts correctly on the next slice.
     */
    public ?int $pendingKickoff = null;

    /** Running score, carried on the state so a paused match resumes exactly. */
    public int $homeGoals = 0;

    public int $awayGoals = 0;

    // Live tactical mentality per side: 'attacking', 'balanced' or 'defensive'.
    // Read by the engine each tick, so a change mid-match takes effect at once.
    public string $homeMentality = 'balanced';

    public string $awayMentality = 'balanced';

    public function mentality(int $side): string
    {
        return $side === 0 ? $this->homeMentality : $this->awayMentality;
    }

    // A set piece the current shot resolves into once it reaches goal: a corner or
    // a goal kick. Null when the shot is saved and held in open play.
    public ?EventType $pendingType = null;

    public int $pendingSide = 0;

    public ?Vec2 $pendingSpot = null;

    /**
     * @param  array<int, PlayerState>  $players  keyed by player id
     */
    public function __construct(
        public array $players,
        public Vec2 $ball,
        public int $carrierId,
    ) {}

    public function carrier(): ?PlayerState
    {
        return $this->players[$this->carrierId] ?? null;
    }

    public function inFlight(): bool
    {
        return $this->carrierId === self::NO_CARRIER;
    }

    /**
     * @return list<PlayerState>
     */
    public function side(int $side): array
    {
        return array_values(array_filter($this->players, fn (PlayerState $p) => $p->side === $side));
    }

    /**
     * A lossless snapshot of the whole match state, so a live match can be
     * persisted between requests and resumed exactly where it paused.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'players' => array_map(fn (PlayerState $p) => $p->toSnapshot(), array_values($this->players)),
            'ball' => $this->ball->pair(),
            'carrierId' => $this->carrierId,
            'possessing' => $this->possessing,
            'clock' => $this->clock,
            'ballTarget' => $this->ballTarget?->pair(),
            'ballTo' => $this->ballTo,
            'ballSpeed' => $this->ballSpeed,
            'ballKind' => $this->ballKind,
            'ballGoal' => $this->ballGoal,
            'holdTicks' => $this->holdTicks,
            'deadBall' => $this->deadBall,
            'pendingType' => $this->pendingType?->value,
            'pendingSide' => $this->pendingSide,
            'pendingSpot' => $this->pendingSpot?->pair(),
            'homeGoals' => $this->homeGoals,
            'awayGoals' => $this->awayGoals,
            'pendingKickoff' => $this->pendingKickoff,
            'homeMentality' => $this->homeMentality,
            'awayMentality' => $this->awayMentality,
        ];
    }

    /**
     * @param  array<string, mixed>  $s
     */
    public static function fromSnapshot(array $s): self
    {
        $players = [];
        /** @var list<array<string, mixed>> $rows */
        $rows = $s['players'];
        foreach ($rows as $row) {
            $player = PlayerState::fromSnapshot($row);
            $players[$player->id] = $player;
        }

        $state = new self($players, Vec2::fromPair($s['ball']), (int) $s['carrierId']);
        $state->possessing = (int) $s['possessing'];
        $state->clock = (float) $s['clock'];
        $state->ballTarget = $s['ballTarget'] !== null ? Vec2::fromPair($s['ballTarget']) : null;
        $state->ballTo = (int) $s['ballTo'];
        $state->ballSpeed = (float) $s['ballSpeed'];
        $state->ballKind = (string) $s['ballKind'];
        $state->ballGoal = (bool) $s['ballGoal'];
        $state->holdTicks = (int) $s['holdTicks'];
        $state->deadBall = (int) $s['deadBall'];
        $state->pendingType = $s['pendingType'] !== null ? EventType::from($s['pendingType']) : null;
        $state->pendingSide = (int) $s['pendingSide'];
        $state->pendingSpot = $s['pendingSpot'] !== null ? Vec2::fromPair($s['pendingSpot']) : null;
        $state->homeGoals = (int) $s['homeGoals'];
        $state->awayGoals = (int) $s['awayGoals'];
        $state->pendingKickoff = isset($s['pendingKickoff']) ? (int) $s['pendingKickoff'] : null;
        $state->homeMentality = (string) ($s['homeMentality'] ?? 'balanced');
        $state->awayMentality = (string) ($s['awayMentality'] ?? 'balanced');

        return $state;
    }
}
