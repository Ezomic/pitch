<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Match\FinishLiveMatch;
use App\Actions\Match\StartLiveMatch;
use App\Models\Fixture;
use App\Models\MatchSession;
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

        return Inertia::render('LiveMatch', [
            'opponentName' => Team::findOrFail($opponentId)->name,
            'moments' => $session->moments,
            'finishUrl' => route('match.live.finish', $fixture),
        ]);
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
