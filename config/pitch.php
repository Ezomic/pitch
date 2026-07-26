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

];
