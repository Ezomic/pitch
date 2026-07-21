<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Match\FinishLiveMatch;
use App\Actions\Match\SetBench;
use App\Actions\Match\StartLiveMatch;
use App\Actions\Match\SubstituteLive;
use App\Actions\Squad\EnsureSquad;
use App\Models\Fixture;
use App\Models\MatchSession;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveMatchController extends Controller
{
    public function show(Request $request, Fixture $fixture, StartLiveMatch $start): Response
    {
        $user = $this->authorizeFixture($request, $fixture);
        $session = $start->handle($user, $fixture);
        $opponentId = $fixture->userIsHome() ? $fixture->away_team_id : $fixture->home_team_id;

        $lineupIds = array_map('intval', array_values($session->lineup ?? []));
        $players = Player::query()->whereIn('id', $lineupIds)->get()->keyBy('id');

        $lineup = [];
        foreach ($session->lineup ?? [] as $slot => $playerId) {
            $player = $players->get($playerId);
            $lineup[] = [
                'slot' => (int) $slot,
                'name' => $player->name,
                'position' => $player->position->value,
                'fitness' => $player->fitness,
                'form' => $player->form,
            ];
        }

        $options = Player::query()->selectableFor($user->id)->whereNotIn('id', $lineupIds)
            ->orderBy('position')->orderBy('name')->get()
            ->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->name,
                'position' => $player->position->value,
            ])->all();

        return Inertia::render('LiveMatch', [
            'opponentName' => Team::findOrFail($opponentId)->name,
            'moments' => $session->moments,
            'lineup' => $lineup,
            'squadOptions' => $options,
            'bench' => array_map('intval', $session->bench ?? []),
            'subsRemaining' => $session->subs_remaining,
            'benchUrl' => route('match.live.bench', $fixture),
            'subUrl' => route('match.live.sub', $fixture),
            'finishUrl' => route('match.live.finish', $fixture),
        ]);
    }

    public function bench(Request $request, Fixture $fixture, SetBench $setBench): RedirectResponse
    {
        $user = $this->authorizeFixture($request, $fixture);
        $data = $request->validate(['players' => ['array'], 'players.*' => ['integer']]);

        $setBench->handle($this->session($user, $fixture), $user, $data['players'] ?? []);

        return to_route('match.live.show', $fixture);
    }

    public function sub(Request $request, Fixture $fixture, SubstituteLive $substitute, EnsureSquad $ensureSquad): RedirectResponse
    {
        $user = $this->authorizeFixture($request, $fixture);
        $data = $request->validate([
            'minute' => ['required', 'integer'],
            'slot' => ['required', 'integer'],
            'in' => ['required', 'integer'],
        ]);

        $opponentId = $fixture->userIsHome() ? $fixture->away_team_id : $fixture->home_team_id;

        $substitute->handle(
            $this->session($user, $fixture),
            $ensureSquad->handle($user),
            Team::findOrFail($opponentId),
            $data['minute'],
            $data['slot'],
            $data['in'],
        );

        return to_route('match.live.show', $fixture);
    }

    public function finish(Request $request, Fixture $fixture, FinishLiveMatch $finish): RedirectResponse
    {
        $user = $this->authorizeFixture($request, $fixture);

        $session = MatchSession::query()
            ->where('user_id', $user->id)
            ->where('fixture_id', $fixture->id)
            ->where('status', 'in_progress')
            ->first();

        if ($session !== null) {
            $finish->handle($session);
        }

        return to_route('season.show');
    }

    private function session(User $user, Fixture $fixture): MatchSession
    {
        return MatchSession::query()
            ->where('user_id', $user->id)
            ->where('fixture_id', $fixture->id)
            ->where('status', 'in_progress')
            ->firstOrFail();
    }

    private function authorizeFixture(Request $request, Fixture $fixture): User
    {
        $user = $this->user($request);

        abort_unless(
            $fixture->season->user_id === $user->id
                && $fixture->involvesUser()
                && ! $fixture->played
                && ! $fixture->youth,
            404,
        );

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
