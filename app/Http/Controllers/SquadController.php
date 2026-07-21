<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Squad\AssignSquadSlot;
use App\Actions\Squad\CompareSetups;
use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\EvaluateSquad;
use App\Actions\Squad\MarginalValue;
use App\Http\Requests\AssignSquadSlotRequest;
use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use App\Sim\Squad\SquadProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SquadController extends Controller
{
    public function edit(Request $request, EnsureSquad $ensureSquad, EvaluateSquad $evaluateSquad): Response
    {
        $squad = $ensureSquad->handle($this->user($request))->load('assignments.player');

        $spent = (int) $squad->assignments->sum(fn ($assignment) => $assignment->player->value());

        return Inertia::render('Squad', [
            'squad' => [
                'id' => $squad->id,
                'name' => $squad->name,
                'slots' => $this->slots($squad),
                'budget' => $squad->budget,
                'spent' => $spent,
                'remaining' => $squad->budget - $spent,
                'formation' => $squad->formation,
                'mentality' => $squad->mentality,
            ],
            'pool' => $this->pool($squad),
            'profile' => $this->profile($evaluateSquad->handle($squad)),
            'formations' => array_values(array_map(
                fn (Formation $formation) => ['id' => $formation->id, 'name' => $formation->name],
                Formation::all(),
            )),
            'mentalities' => array_map(
                fn (Mentality $mentality) => ['id' => $mentality->value, 'name' => $mentality->label()],
                Mentality::cases(),
            ),
        ]);
    }

    public function whatIf(Request $request, EnsureSquad $ensureSquad, MarginalValue $marginalValue): Response
    {
        $squad = $ensureSquad->handle($this->user($request));

        return Inertia::render('SquadWhatIf', [
            'marginal' => $marginalValue->handle($squad),
        ]);
    }

    public function compare(Request $request, EnsureSquad $ensureSquad, CompareSetups $compareSetups): Response
    {
        $squad = $ensureSquad->handle($this->user($request));

        $formations = array_keys(Formation::all());
        $mentalities = array_column(Mentality::cases(), 'value');

        $data = $request->validate([
            'formationA' => ['nullable', Rule::in($formations)],
            'mentalityA' => ['nullable', Rule::in($mentalities)],
            'formationB' => ['nullable', Rule::in($formations)],
            'mentalityB' => ['nullable', Rule::in($mentalities)],
        ]);

        $setup = [
            'formationA' => $data['formationA'] ?? $squad->formation,
            'mentalityA' => $data['mentalityA'] ?? $squad->mentality,
            'formationB' => $data['formationB'] ?? '442',
            'mentalityB' => $data['mentalityB'] ?? 'attacking',
        ];

        return Inertia::render('SquadCompare', [
            'setup' => $setup,
            'profiles' => $compareSetups->handle(
                $squad,
                $setup['formationA'],
                $setup['mentalityA'],
                $setup['formationB'],
                $setup['mentalityB'],
            ),
            'formations' => array_values(array_map(
                fn (Formation $formation) => ['id' => $formation->id, 'name' => $formation->name],
                Formation::all(),
            )),
            'mentalities' => array_map(
                fn (Mentality $mentality) => ['id' => $mentality->value, 'name' => $mentality->label()],
                Mentality::cases(),
            ),
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

    public function tactics(Request $request, EnsureSquad $ensureSquad): RedirectResponse
    {
        $data = $request->validate([
            'formation' => ['required', Rule::in(array_keys(Formation::all()))],
            'mentality' => ['required', Rule::in(array_column(Mentality::cases(), 'value'))],
        ]);

        $ensureSquad->handle($this->user($request))->update($data);

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
        foreach (Formation::fromId($squad->formation)->layout as $slot => [$zone, $position]) {
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
            ->selectableFor($squad->user_id)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        // The user's own injured or suspended players are shown too, greyed out.
        $unavailable = Player::query()
            ->where('user_id', $squad->user_id)
            ->where('is_youth', false)
            ->where(fn ($query) => $query->where('injured_weeks', '>', 0)->orWhere('suspended_weeks', '>', 0))
            ->orderBy('name')
            ->get();

        $pool = [];
        foreach ($players->concat($unavailable) as $player) {
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
            'age' => $player->age,
            'vision' => $player->vision,
            'passing' => $player->passing,
            'dribbling' => $player->dribbling,
            'finishing' => $player->finishing,
            'tackling' => $player->tackling,
            'pace' => $player->pace,
            'value' => $player->value(),
            'fitness' => $player->fitness,
            'form' => $player->form,
            'trait' => $player->trait,
            'injuredWeeks' => $player->injured_weeks,
            'suspendedWeeks' => $player->suspended_weeks,
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
