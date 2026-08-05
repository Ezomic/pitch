<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LiveSim\AdvanceMatch;
use App\Actions\LiveSim\SetMentality;
use App\Actions\LiveSim\StartMatch;
use App\Actions\LiveSim\Substitute;
use App\Actions\Season\RateClubs;
use App\Actions\Squad\EnsureSquad;
use App\Http\Requests\LiveSim\SetMentalityRequest;
use App\Http\Requests\LiveSim\SubstituteRequest;
use App\Models\Fixture;
use App\Models\LiveMatch;
use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveSimController extends Controller
{
    public function __construct(
        private readonly RateClubs $rateClubs = new RateClubs,
    ) {}

    /**
     * Resume the match already in progress, or start one when there is none.
     * A plain page load must never kick off a fresh match: refreshing mid-game
     * used to abandon it and start another, losing the match being played.
     */
    public function show(Request $request, EnsureSquad $ensureSquad, StartMatch $start): Response
    {
        $user = $this->user($request);
        $squad = $ensureSquad->handle($user);
        $match = LiveMatch::inProgressFor($user) ?? $this->kickOff($user, $squad, $start);

        $lineupIds = array_map('intval', array_values($squad->assignments()->pluck('player_id', 'slot')->all()));

        // Drawn from the same pool the lineup was picked from. Filtering on
        // user_id alone left the bench empty for a default squad, whose players
        // are unowned pool players, so no substitution could ever be made.
        $bench = Player::query()->selectableFor($user->id)->whereNotIn('id', $lineupIds)
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

        // How the two sides rate in the division they share, so the size of the
        // task is clear before kick-off.
        $rated = $this->rateClubs->handle($squad);

        return Inertia::render('LiveSim', [
            'matchId' => $match->id,
            'players' => $match->players,
            'homeName' => $match->home_name,
            'awayName' => $match->away_name,
            'homeStars' => $rated[RateClubs::USER_KEY]['league'] ?? null,
            'awayStars' => $match->opponent_team_id !== null
                ? ($rated[$match->opponent_team_id]['league'] ?? null)
                : null,
            'totalTicks' => $match->total_ticks,
            'subsRemaining' => $match->subs_remaining,
            'onPitch' => $onPitch,
            'bench' => $bench,
            // Where the match already is. A resumed match picks up from its
            // current tick with the score and feed it had built up: the frames
            // it already played are not persisted, so the action so far is read
            // in the feed rather than re-watched.
            'currentTick' => $match->current_tick,
            'homeGoals' => $match->home_goals,
            'awayGoals' => $match->away_goals,
            'moments' => $match->moments,
            'mentality' => $match->pitch_state['homeMentality'] ?? 'balanced',
            // A league match counts: the score is written onto the fixture at
            // full time. A friendly does not.
            'competitive' => $match->fixture_id !== null,
            'seasonUrl' => route('season.show'),
        ]);
    }

    /**
     * Deliberately walk away from the current match and kick off a friendly.
     * The league fixture is not restarted this way: it is played once, for real.
     */
    public function store(Request $request, EnsureSquad $ensureSquad, StartMatch $start): RedirectResponse
    {
        $user = $this->user($request);
        $start->handle($user, $ensureSquad->handle($user));

        return to_route('play.show');
    }

    /**
     * The manager's own league fixture when one is due, so playing it out is
     * what /play does by default; a friendly otherwise. The league match used to
     * be played in a different engine entirely, at a different URL.
     */
    private function kickOff(User $user, Squad $squad, StartMatch $start): LiveMatch
    {
        $fixture = Fixture::dueFor($user);

        return $fixture instanceof Fixture
            ? $start->forFixture($user, $squad, $fixture)
            : $start->handle($user, $squad);
    }

    public function advance(Request $request, LiveMatch $match, AdvanceMatch $advance): JsonResponse
    {
        $this->authorizeMatch($request, $match);
        $chunk = max(6, min(120, (int) $request->integer('ticks', 30)));

        return response()->json($advance->handle($match, $chunk));
    }

    public function sub(SubstituteRequest $request, LiveMatch $match, Substitute $substitute): JsonResponse
    {
        $user = $this->authorizeMatch($request, $match);

        $player = Player::query()->selectableFor($user->id)
            ->where('id', $request->integer('player_id'))->firstOrFail();
        $substitute->handle($match, $request->integer('out_slot'), $player);

        return response()->json(['subsRemaining' => $match->subs_remaining]);
    }

    public function mentality(SetMentalityRequest $request, LiveMatch $match, SetMentality $setMentality): JsonResponse
    {
        $this->authorizeMatch($request, $match);
        $mentality = $request->mentality();

        $setMentality->handle($match, $mentality);

        return response()->json(['mentality' => $mentality->value]);
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
