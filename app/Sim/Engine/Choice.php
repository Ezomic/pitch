<?php

declare(strict_types=1);

namespace App\Sim\Engine;

use App\Sim\Domain\Decision;

final readonly class Choice
{
    public function __construct(
        public Option $option,
        public Decision $decision,
    ) {}
}
