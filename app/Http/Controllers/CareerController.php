<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Career\CreateCareer;
use App\Actions\Career\DeleteCareer;
use App\Actions\Career\SwitchCareer;
use App\Http\Requests\Career\StoreCareerRequest;
use App\Models\Career;
use App\Models\Season;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CareerController extends Controller
{
    /** Every save the manager holds, newest play first. */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $active = $user->currentCareer();

        return Inertia::render('Careers', [
            'careers' => $this->careers($user, $active),
            'activeId' => $active->id,
        ]);
    }

    public function store(StoreCareerRequest $request, CreateCareer $create): RedirectResponse
    {
        $create->handle($this->user($request), $request->careerName(), $request->careerType());

        return to_route('careers.index');
    }

    public function switch(Request $request, Career $career, SwitchCareer $switch): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($career->user_id === $user->id, 403);

        $switch->handle($user, $career);

        return to_route('dashboard');
    }

    public function rename(StoreCareerRequest $request, Career $career): RedirectResponse
    {
        abort_unless($career->user_id === $this->user($request)->id, 403);

        $career->forceFill(['name' => $request->careerName()])->save();

        return to_route('careers.index');
    }

    public function destroy(Request $request, Career $career, DeleteCareer $delete): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($career->user_id === $user->id, 403);

        if (! $delete->handle($user, $career)) {
            return to_route('careers.index')->withErrors([
                'career' => 'You cannot delete your only save.',
            ]);
        }

        return to_route('careers.index');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function careers(User $user, Career $active): array
    {
        // Newest wins a tie: two saves touched in the same second would otherwise
        // come back in whatever order the database felt like.
        $careers = $user->careers()->orderByDesc('last_played_at')->orderByDesc('id')->get();

        // Counted per save rather than through the scoped relations, which only
        // ever describe the one being played.
        $squads = Squad::query()->whereIn('career_id', $careers->pluck('id'))->get()->keyBy('career_id');
        $seasons = Season::query()->whereIn('career_id', $careers->pluck('id'))->get()->groupBy('career_id');

        $rows = $careers->map(function (Career $career) use ($active, $squads, $seasons): array {
            $played = $seasons->get($career->id);

            return [
                'id' => $career->id,
                'name' => $career->name,
                'type' => $career->type,
                'active' => $career->id === $active->id,
                'lastPlayedAt' => $career->last_played_at?->toDateString(),
                'squadName' => $squads->get($career->id)?->name,
                'division' => $squads->get($career->id)?->division,
                'seasons' => $played?->count() ?? 0,
                'currentSeason' => $played?->whereNull('completed_at')->first()?->number,
            ];
        })->all();

        return array_values($rows);
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
