<?php

declare(strict_types=1);

namespace App\Sim\Runner;

use Illuminate\Support\Facades\Process;
use JsonException;

final readonly class CliMatchRunner implements MatchRunner
{
    public function __construct(
        private ?string $binary,
        private int $timeout = 60,
    ) {}

    public function run(RunnerInput $input): RunnerResult
    {
        if ($this->binary === null || $this->binary === '') {
            throw new MatchRunnerException('No match runner binary is configured.');
        }

        $process = Process::timeout($this->timeout)
            ->input(json_encode($input->toArray(), JSON_THROW_ON_ERROR))
            ->run([$this->binary]);

        if ($process->failed()) {
            throw new MatchRunnerException('The match runner exited with code '.$process->exitCode().': '.trim($process->errorOutput()));
        }

        try {
            $data = json_decode($process->output(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MatchRunnerException('The match runner did not return JSON.', previous: $e);
        }

        if (! is_array($data)) {
            throw new MatchRunnerException('The match runner did not return a JSON object.');
        }

        return RunnerResult::fromArray($data);
    }
}
