<?php

declare(strict_types=1);

namespace App\Actions\LiveSim;

use App\Models\LiveMatch;
use App\Models\Squad;
use App\Models\User;
use App\Sim\Pitch\LivePitch;
use App\Sim\Pitch\PositionalEngine;
use App\Sim\Squad\TeamSetup;

/**
 * Begin a live positional match for the user's squad against an opponent. The
 * kickoff engine state (and its Rng position) are serialised onto the LiveMatch
 * so the tick loop can be advanced a slice at a time across requests.
 */
class StartMatch
{
    public function __construct(
        private readonly PositionalEngine $engine = new PositionalEngine,
        private readonly LivePitch $live = new LivePitch,
    ) {}

    public function handle(User $user, Squad $squad): LiveMatch
    {
        $setup = $squad->setup();
        $opponent = TeamSetup::baseline();

        $names = [];
        foreach ($squad->assignments()->with('player')->get() as $assignment) {
            $names[$assignment->slot] = $assignment->player->name;
        }
        foreach ($setup->formation->slots() as $slot) {
            $names[$slot] ??= "Slot {$slot}";
        }

        $seed = random_int(1, 2_000_000_000);
        [$state, $rng] = $this->engine->start($setup->attackers(), $opponent->attackers(), $seed);

        return LiveMatch::create([
            'user_id' => $user->id,
            'seed' => $seed,
            'current_tick' => 0,
            'total_ticks' => $this->engine->totalTicks(),
            'pitch_state' => $state->toSnapshot(),
            'rng_state' => $rng->stateValue(),
            'home_goals' => 0,
            'away_goals' => 0,
            'home_name' => 'Your squad',
            'away_name' => 'Opposition',
            'players' => $this->live->players($names),
            'moments' => [],
            'subs_remaining' => 5,
            'status' => 'live',
        ]);
    }
}
