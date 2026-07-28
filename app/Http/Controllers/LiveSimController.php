<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LiveSim\AdvanceMatch;
use App\Actions\LiveSim\SetMentality;
use App\Actions\LiveSim\StartMatch;
use App\Actions\LiveSim\Substitute;
use App\Actions\Squad\EnsureSquad;
use App\Models\LiveMatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveSimController extends Controller
{
    public function show(Request $request, EnsureSquad $ensureSquad, StartMatch $start): Response
    {
        $user = $this->user($request);
        $squad = $ensureSquad->handle($user);
        $match = $start->handle($user, $squad);

        $lineupSlots = array_map('intval', array_keys(iterator_to_array($squad->assignments()->pluck('player_id', 'slot'))));
        $lineupIds = array_map('intval', array_values($squad->assignments()->pluck('player_id', 'slot')->all()));

        $bench = Player::query()->where('user_id', $user->id)->whereNotIn('id', $lineupIds)
            ->orderBy('position')->orderBy('name')->get()
            ->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->name,
                'position' => $player->position->value,
            ])->values()->all();

        $onPitch = [];
        foreach ($squad->assignments()->with('player')->get() as $assignment) {
            $onPitch[] = ['slot' => (int) $assignment->slot, 'name' => $assignment->player->name];
        }

        return Inertia::render('LiveSim', [
            'matchId' => $match->id,
            'players' => $match->players,
            'homeName' => $match->home_name,
            'awayName' => $match->away_name,
            'totalTicks' => $match->total_ticks,
            'subsRemaining' => $match->subs_remaining,
            'onPitch' => $onPitch,
            'bench' => $bench,
        ]);
    }

    public function advance(Request $request, LiveMatch $match, AdvanceMatch $advance): JsonResponse
    {
        $this->authorizeMatch($request, $match);
        $chunk = max(6, min(120, (int) $request->integer('ticks', 30)));

        return response()->json($advance->handle($match, $chunk));
    }

    public function sub(Request $request, LiveMatch $match, Substitute $substitute): JsonResponse
    {
        $user = $this->authorizeMatch($request, $match);
        $data = $request->validate([
            'out_slot' => ['required', 'integer', 'min:1', 'max:10'],
            'player_id' => ['required', 'integer'],
        ]);

        $player = Player::query()->where('user_id', $user->id)
            ->where('id', $data['player_id'])->firstOrFail();
        $substitute->handle($match, $data['out_slot'], $player);

        return response()->json(['subsRemaining' => $match->subs_remaining]);
    }

    public function mentality(Request $request, LiveMatch $match, SetMentality $setMentality): JsonResponse
    {
        $this->authorizeMatch($request, $match);
        $data = $request->validate(['mentality' => ['required', 'string']]);

        $setMentality->handle($match, $data['mentality']);

        return response()->json(['mentality' => $data['mentality']]);
    }

    private function authorizeMatch(Request $request, LiveMatch $match): User
    {
        $user = $this->user($request);
        abort_unless($match->user_id === $user->id, 403);

        return $user;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
