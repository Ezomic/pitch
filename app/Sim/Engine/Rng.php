<?php

declare(strict_types=1);

namespace App\Sim\Engine;

/**
 * Deterministic 32-bit xorshift PRNG. Same seed always yields the same stream.
 *
 * All arithmetic is masked to 32 bits so it never overflows PHP's 64-bit int
 * into a float, which would break reproducibility across machines.
 */
final class Rng
{
    private const int MASK = 0xFFFFFFFF;

    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $this->scramble($seed);
    }

    /** The internal state, so a live match can be paused and resumed exactly. */
    public function stateValue(): int
    {
        return $this->state;
    }

    /** Rebuild an Rng at a saved point in its stream. */
    public static function fromState(int $state): self
    {
        $rng = new self(0);
        $rng->state = $state;

        return $rng;
    }

    public function next(): float
    {
        return $this->nextInt() / 4294967296.0;
    }

    public function nextInt(): int
    {
        $x = $this->state;
        $x ^= ($x << 13) & self::MASK;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & self::MASK;
        $this->state = $x & self::MASK;

        return $this->state;
    }

    /**
     * Uniform integer in [0, $bound).
     */
    public function below(int $bound): int
    {
        return (int) ($this->next() * $bound);
    }

    private function scramble(int $seed): int
    {
        $s = ($seed ^ 0x9E3779B9) & self::MASK;
        $s = ($s + 0x7ED55D16 + (($s << 12) & self::MASK)) & self::MASK;
        $s = ($s ^ 0xC761C23C ^ ($s >> 19)) & self::MASK;
        $s = ($s + 0x165667B1 + (($s << 5) & self::MASK)) & self::MASK;

        return $s === 0 ? 1 : $s;
    }
}
