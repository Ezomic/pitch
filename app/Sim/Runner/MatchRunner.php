<?php

declare(strict_types=1);

namespace App\Sim\Runner;

interface MatchRunner
{
    /**
     * @throws MatchRunnerException
     */
    public function run(RunnerInput $input): RunnerResult;
}
