<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\League\BuildFixtureReplay;
use App\Models\Career;
use App\Models\Fixture;
use App\Models\LeagueRound;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeagueReplayController extends Controller
{
    /** Any member can watch, once the matchday is over and the runner left a log. */
    public function show(Request $request, Career $career, Fixture $fixture, BuildFixtureReplay $replay): Response
    {
        abort_unless($career->isLeague(), 404);

        $user = $request->user();
        abort_unless($user instanceof User && $career->seatFor($user) !== null, 403);

        abort_unless($fixture->season->career_id === $career->id, 404);
        abort_unless($fixture->hasStoredReplay(), 404);
        abort_unless(LeagueRound::query()
            ->where('career_id', $career->id)
            ->where('season_id', $fixture->season_id)
            ->where('matchday', $fixture->matchday)
            ->where('status', LeagueRound::RESOLVED)
            ->exists(), 404);

        return Inertia::render('LeagueReplay', [
            'career' => ['id' => $career->id, 'name' => $career->name],
            'replay' => $replay->handle($fixture->load(['homeTeam', 'awayTeam'])),
        ]);
    }
}
