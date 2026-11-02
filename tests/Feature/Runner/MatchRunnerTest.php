<?php

declare(strict_types=1);

use App\Models\Team;
use App\Sim\Runner\CliMatchRunner;
use App\Sim\Runner\MatchRunner;
use App\Sim\Runner\MatchRunnerException;
use App\Sim\Runner\RunnerInput;
use App\Sim\Runner\RunnerResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\Support\FakeMatchRunner;

uses(RefreshDatabase::class);

function runnerInput(): RunnerInput
{
    $home = Team::factory()->create(['formation' => '442', 'mentality' => 'attacking'])->fresh();
    $away = Team::factory()->create(['formation' => '532', 'mentality' => 'defensive'])->fresh();

    return new RunnerInput($home->setup(), $away->setup(), 4242);
}

it('sends lineups, formation, mentality, the home flag and the seed', function () {
    $payload = runnerInput()->toArray();

    expect($payload['schema'])->toBe(1)
        ->and($payload['seed'])->toBe(4242)
        ->and($payload['home']['home'])->toBeTrue()
        ->and($payload['away']['home'])->toBeFalse()
        ->and($payload['home']['formation'])->toBe('442')
        ->and($payload['home']['mentality'])->toBe('attacking')
        ->and($payload['away']['formation'])->toBe('532')
        ->and($payload['away']['mentality'])->toBe('defensive')
        ->and($payload['home']['players'])->toHaveCount(10)
        ->and($payload['home']['players'][0])->toHaveKeys(['slot', 'vision', 'passing', 'dribbling', 'finishing', 'tackling', 'pace', 'fitness'])
        ->and($payload['home'])->toHaveKeys(['goalkeeping', 'set_piece']);
});

it('reads a golden runner result', function () {
    $result = FakeMatchRunner::golden('win');

    expect($result->version)->toBe('nutmeg-0.4.2')
        ->and($result->homeGoals)->toBe(2)
        ->and($result->awayGoals)->toBe(1)
        ->and($result->stats['shots'])->toBe([11, 7])
        ->and($result->events)->toHaveCount(3);
});

it('rejects a result that does not match the schema', function (array $data) {
    RunnerResult::fromArray($data);
})->throws(MatchRunnerException::class, 'does not match the schema')->with([
    'no version' => [['home_goals' => 1, 'away_goals' => 0]],
    'empty version' => [['version' => '', 'home_goals' => 1, 'away_goals' => 0]],
    'goals not an int' => [['version' => 'v', 'home_goals' => '1', 'away_goals' => 0]],
    'negative goals' => [['version' => 'v', 'home_goals' => 1, 'away_goals' => -1]],
    'missing away goals' => [['version' => 'v', 'home_goals' => 1]],
    'stats not an array' => [['version' => 'v', 'home_goals' => 1, 'away_goals' => 0, 'stats' => 3]],
    'events not an array' => [['version' => 'v', 'home_goals' => 1, 'away_goals' => 0, 'events' => 'x']],
]);

it('runs the binary with the match on stdin and reads the result from stdout', function () {
    Process::fake(['*' => Process::result((string) file_get_contents(__DIR__.'/../../Fixtures/runner/win.json'))]);

    $result = (new CliMatchRunner('/opt/nutmeg/runner'))->run(runnerInput());

    expect($result->homeGoals)->toBe(2);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['/opt/nutmeg/runner']
        && json_decode((string) $process->input, true)['seed'] === 4242);
});

it('fails when the runner exits non-zero', function () {
    Process::fake(['*' => Process::result('', 'segfault', 139)]);

    (new CliMatchRunner('/opt/nutmeg/runner'))->run(runnerInput());
})->throws(MatchRunnerException::class, 'exited with code 139: segfault');

it('fails when the runner prints something that is not JSON', function () {
    Process::fake(['*' => Process::result('not json')]);

    (new CliMatchRunner('/opt/nutmeg/runner'))->run(runnerInput());
})->throws(MatchRunnerException::class, 'did not return JSON');

it('fails when the runner prints JSON that is not an object', function () {
    Process::fake(['*' => Process::result('5')]);

    (new CliMatchRunner('/opt/nutmeg/runner'))->run(runnerInput());
})->throws(MatchRunnerException::class, 'did not return a JSON object');

it('fails when no binary is configured', function (?string $binary) {
    (new CliMatchRunner($binary))->run(runnerInput());
})->throws(MatchRunnerException::class, 'No match runner binary')->with([null, '']);

it('binds the CLI runner from config', function () {
    config(['pitch.runner.binary' => '/opt/nutmeg/runner']);

    expect(app(MatchRunner::class))->toBeInstanceOf(CliMatchRunner::class);
});

it('runs a match through the real binary', function () {
    $result = app(MatchRunner::class)->run(runnerInput());

    expect($result->version)->not->toBe('')
        ->and($result->homeGoals)->toBeGreaterThanOrEqual(0);
})->skip(
    fn (): bool => ! is_string(env('PITCH_RUNNER_BINARY')) || ! is_executable((string) env('PITCH_RUNNER_BINARY')),
    'The Nutmeg runner binary is not installed (set PITCH_RUNNER_BINARY).',
);
