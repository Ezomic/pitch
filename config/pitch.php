<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Match engine
    |--------------------------------------------------------------------------
    |
    | Which match engine simulates a game. 'zone' is the original ball-only
    | engine; 'positional' is the two-way engine with real player positions
    | (App\Sim\Pitch). Kept behind a flag while the positional engine is built
    | in stages so nothing user-facing changes until it is deliberately cut over.
    |
    */

    'engine' => env('PITCH_ENGINE', 'zone'),

    /*
    |--------------------------------------------------------------------------
    | Nutmeg runner
    |--------------------------------------------------------------------------
    |
    | The headless Nutmeg binary that resolves league matchdays for careers set
    | to the nutmeg engine. It reads one JSON match on stdin and writes one JSON
    | result on stdout. Leave the binary empty until a build exists on the box.
    |
    */

    'runner' => [
        'binary' => env('PITCH_RUNNER_BINARY'),
        'timeout' => (int) env('PITCH_RUNNER_TIMEOUT', 60),
        'backoff' => [10, 60],
    ],

];
