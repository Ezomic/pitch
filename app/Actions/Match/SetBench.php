<?php

declare(strict_types=1);

namespace App\Actions\Match;

use App\Models\MatchSession;
use App\Models\Player;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SetBench
{
    private const int MAX_BENCH = 7;

    /**
     * Name the matchday bench: selectable players not already in the XI.
     *
     * @param  list<int>  $playerIds
     *
     * @throws ValidationException
     */
    public function handle(MatchSession $session, User $user, array $playerIds): void
    {
        $playerIds = array_values(array_unique(array_map('intval', $playerIds)));

        if (count($playerIds) > self::MAX_BENCH) {
            throw ValidationException::withMessages(['bench' => 'A bench holds at most seven players.']);
        }

        $inXi = array_map('intval', array_values($session->lineup ?? []));
        $selectable = Player::query()->selectableFor($user->id)->whereIn('id', $playerIds)->pluck('id')->all();

        foreach ($playerIds as $id) {
            if (in_array($id, $inXi, true) || ! in_array($id, $selectable, true)) {
                throw ValidationException::withMessages(['bench' => 'That player cannot sit on the bench.']);
            }
        }

        $session->update(['bench' => $playerIds]);
    }
}
