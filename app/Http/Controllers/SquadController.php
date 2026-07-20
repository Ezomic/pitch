<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Squad\AssignSquadSlot;
use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\EvaluateSquad;
use App\Http\Requests\AssignSquadSlotRequest;
use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use App\Sim\Engine\Roster;
use App\Sim\Squad\SquadProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SquadController extends Controller
{
    public function edit(Request $request, EnsureSquad $ensureSquad, EvaluateSquad $evaluateSquad): Response
    {
        $squad = $ensureSquad->handle($this->user($request))->load('assignments.player');

        return Inertia::render('Squad', [
            'squad' => [
                'id' => $squad->id,
                'name' => $squad->name,
                'slots' => $this->slots($squad),
            ],
            'pool' => $this->pool($squad),
            'profile' => $this->profile($evaluateSquad->handle($squad)),
        ]);
    }

    public function assign(
        AssignSquadSlotRequest $request,
        EnsureSquad $ensureSquad,
        AssignSquadSlot $assignSquadSlot,
    ): RedirectResponse {
        $squad = $ensureSquad->handle($this->user($request));

        $assignSquadSlot->handle($squad, $request->slot(), $request->playerId());

        return to_route('squad.edit');
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function slots(Squad $squad): array
    {
        $bySlot = $squad->assignments->keyBy('slot');

        $slots = [];
        foreach (Roster::formation() as $slot => [$zone, $position]) {
            $player = $bySlot->get($slot)?->player;

            $slots[] = [
                'slot' => $slot,
                'zone' => ['x' => $zone->x, 'y' => $zone->y],
                'position' => $position->value,
                'player' => $player !== null ? $this->player($player) : null,
            ];
        }

        return $slots;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pool(Squad $squad): array
    {
        $assignedSlot = $squad->assignments->keyBy('player_id');

        $players = Player::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $pool = [];
        foreach ($players as $player) {
            $pool[] = [
                ...$this->player($player),
                'slot' => $assignedSlot->get($player->id)?->slot,
            ];
        }

        return $pool;
    }

    /**
     * @return array<string, mixed>
     */
    private function player(Player $player): array
    {
        return [
            'id' => $player->id,
            'name' => $player->name,
            'position' => $player->position->value,
            'vision' => $player->vision,
            'passing' => $player->passing,
            'dribbling' => $player->dribbling,
            'finishing' => $player->finishing,
            'tackling' => $player->tackling,
            'pace' => $player->pace,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function profile(SquadProfile $profile): array
    {
        return [
            'meanDecisionGap' => $profile->meanDecisionGap,
            'progressivePassShare' => $profile->progressivePassShare,
            'chancesPer90' => $profile->chancesPer90,
            'goalsPer90' => $profile->goalsPer90,
            'chancesConcededPer90' => $profile->chancesConcededPer90,
            'goalsConcededPer90' => $profile->goalsConcededPer90,
        ];
    }
}
