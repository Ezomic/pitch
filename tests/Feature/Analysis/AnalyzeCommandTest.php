<?php

declare(strict_types=1);

it('runs the analyze command and reports metrics', function () {
    $this->artisan('pitch:analyze', ['--seeds' => 3])
        ->expectsOutputToContain('Simulating 3 matches')
        ->assertExitCode(0);
});
