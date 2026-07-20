<?php

declare(strict_types=1);

namespace App\Sim\Squad;

final readonly class MatchMoment
{
    public function __construct(
        public int $minute,
        public string $kind,
        public string $text,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'minute' => $this->minute,
            'kind' => $this->kind,
            'text' => $this->text,
        ];
    }
}
