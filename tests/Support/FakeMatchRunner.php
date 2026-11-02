<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Sim\Runner\MatchRunner;
use App\Sim\Runner\MatchRunnerException;
use App\Sim\Runner\RunnerInput;
use App\Sim\Runner\RunnerResult;

final class FakeMatchRunner implements MatchRunner
{
    /** @var list<RunnerInput> */
    public array $inputs = [];

    public int $failures;

    public function __construct(private readonly string $golden = 'win', int $failures = 0)
    {
        $this->failures = $failures;
    }

    public function run(RunnerInput $input): RunnerResult
    {
        $this->inputs[] = $input;

        if ($this->failures > 0) {
            $this->failures--;

            throw new MatchRunnerException('The runner crashed.');
        }

        return self::golden($this->golden);
    }

    public static function golden(string $name): RunnerResult
    {
        /** @var array<mixed> $data */
        $data = json_decode((string) file_get_contents(__DIR__."/../Fixtures/runner/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);

        return RunnerResult::fromArray($data);
    }
}
