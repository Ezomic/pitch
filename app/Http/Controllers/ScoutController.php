<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Scout\AssignScout;
use App\Actions\Scout\EnsureScouts;
use App\Actions\Scout\HireScout;
use App\Actions\Scout\RecallScout;
use App\Actions\Season\EnsureSeason;
use App\Actions\Squad\EnsureSquad;
use App\Enums\ScoutStatus;
use App\Models\Player;
use App\Models\Scout;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScoutController extends Controller
{
    public function index(Request $request, EnsureScouts $ensureScouts, EnsureSquad $ensureSquad, EnsureSeason $ensureSeason): Response
    {
        $user = $this->user($request);
        $ensureScouts->handle($user);

        $squad = $ensureSquad->handle($user);
        $season = $ensureSeason->handle($user);
        $scouts = $user->scouts()->orderByDesc('rating')->orderBy('name')->get();

        return Inertia::render('Scouting', [
            'budget' => $squad->budget,
            'currentDate' => $season->current_date->toDateString(),
            'academyCount' => Player::query()->where('user_id', $user->id)->where('is_youth', true)->count(),
            'staff' => $scouts->whereNotIn('status', [ScoutStatus::Available])->values()
                ->map(fn (Scout $scout) => $this->scout($scout))->all(),
            'market' => $scouts->where('status', ScoutStatus::Available)->values()
                ->map(fn (Scout $scout) => $this->scout($scout))->all(),
        ]);
    }

    public function hire(Request $request, Scout $scout, HireScout $hireScout, EnsureSquad $ensureSquad): RedirectResponse
    {
        $this->authorizeScout($request, $scout);

        $hireScout->handle($scout, $ensureSquad->handle($this->user($request)));

        return to_route('scouts.index');
    }

    public function assign(Request $request, Scout $scout, AssignScout $assignScout, EnsureSeason $ensureSeason): RedirectResponse
    {
        $this->authorizeScout($request, $scout);

        $season = $ensureSeason->handle($this->user($request));
        $assignScout->handle($scout, CarbonImmutable::parse($season->current_date));

        return to_route('scouts.index');
    }

    public function recall(Request $request, Scout $scout, RecallScout $recallScout): RedirectResponse
    {
        $this->authorizeScout($request, $scout);

        $recallScout->handle($scout);

        return to_route('scouts.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function scout(Scout $scout): array
    {
        return [
            'id' => $scout->id,
            'name' => $scout->name,
            'rating' => $scout->rating,
            'cost' => $scout->cost(),
            'status' => $scout->status->value,
            'statusLabel' => $scout->status->label(),
            'nextDelivery' => $scout->next_delivery_on?->toDateString(),
        ];
    }

    private function authorizeScout(Request $request, Scout $scout): void
    {
        abort_unless($scout->user_id === $this->user($request)->id, 404);
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
