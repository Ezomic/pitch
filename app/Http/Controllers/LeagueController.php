<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Career\ClaimClub;
use App\Actions\Career\InviteToLeague;
use App\Actions\Career\JoinLeague;
use App\Actions\Career\RemoveMember;
use App\Actions\League\CurrentRound;
use App\Actions\League\ResolveRound;
use App\Actions\League\SubmitOrder;
use App\Http\Requests\League\StoreOrderRequest;
use App\Models\Career;
use App\Models\CareerInvitation;
use App\Models\CareerMembership;
use App\Models\Fixture;
use App\Models\LeagueRound;
use App\Models\Team;
use App\Models\User;
use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class LeagueController extends Controller
{
    /** The lobby: who is in, which club they run, and what is still free. */
    public function show(Request $request, Career $career, CurrentRound $currentRound, ResolveRound $resolve): Response
    {
        $user = $this->user($request);
        $seat = $this->seat($career, $user);

        // A deadline is only real if something enforces it. Every visit to the
        // lobby is a chance to notice one has passed and play the round.
        $round = $currentRound->handle($career);

        if ($round instanceof LeagueRound && $resolve->handle($round)) {
            $round = $currentRound->handle($career);
        }

        $memberships = $career->memberships()->with(['user', 'team'])->orderBy('id')->get();
        $taken = $memberships->pluck('team_id')->filter()->all();

        return Inertia::render('League', [
            'round' => $round instanceof LeagueRound ? $this->round($round, $seat, $memberships) : null,
            'formations' => array_values(array_map(
                fn (Formation $f): array => ['id' => $f->id, 'name' => $f->name],
                Formation::all(),
            )),
            'mentalities' => array_map(
                fn (Mentality $m): array => ['id' => $m->value, 'name' => ucfirst($m->value)],
                Mentality::cases(),
            ),
            'career' => ['id' => $career->id, 'name' => $career->name, 'roundHours' => $career->round_hours],
            'isOwner' => $seat->isOwner(),
            'yourTeamId' => $seat->team_id,
            'members' => $memberships->map(fn (CareerMembership $m): array => [
                'id' => $m->id,
                'name' => $m->user->name,
                'club' => $m->team?->name,
                'owner' => $m->isOwner(),
                'you' => $m->user_id === $user->id,
            ])->all(),
            // Everything nobody has claimed is run by the AI until somebody does.
            'availableClubs' => Team::query()->where('is_youth', false)
                ->whereNotIn('id', $taken)->orderBy('name')
                ->get()->map(fn (Team $t): array => [
                    'id' => $t->id, 'name' => $t->name, 'division' => $t->division,
                ])->all(),
            'invitations' => $seat->isOwner()
                ? $career->invitations()->latest('id')->get()->map(fn (CareerInvitation $i): array => [
                    'id' => $i->id,
                    'url' => route('league.join', $i->token),
                    'status' => $i->status(),
                    'expiresAt' => $i->expires_at?->toDateString(),
                ])->all()
                : [],
        ]);
    }

    public function invite(Request $request, Career $career, InviteToLeague $invite): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->seat($career, $user)->isOwner(), 403);

        $invite->handle($career, $user);

        return to_route('league.show', $career);
    }

    /** Open an invitation. Joining is a click, not an automatic side effect. */
    public function join(Request $request, string $token, JoinLeague $join): RedirectResponse
    {
        $invitation = CareerInvitation::query()->where('token', $token)->first();

        if (! $invitation instanceof CareerInvitation) {
            abort(404);
        }

        $user = $this->user($request);
        $seat = $join->handle($invitation, $user);

        if ($seat === null) {
            return to_route('careers.index')->withErrors([
                'career' => 'That invitation has been used or has expired.',
            ]);
        }

        $user->forceFill(['current_career_id' => $invitation->career_id])->save();

        return to_route('league.show', $invitation->career_id);
    }

    public function claim(Request $request, Career $career, Team $team, ClaimClub $claim): RedirectResponse
    {
        $seat = $this->seat($career, $this->user($request));

        if (! $claim->handle($seat, $team)) {
            return to_route('league.show', $career)->withErrors([
                'club' => 'That club has already been taken.',
            ]);
        }

        return to_route('league.show', $career);
    }

    public function release(Request $request, Career $career, ClaimClub $claim): RedirectResponse
    {
        $claim->release($this->seat($career, $this->user($request)));

        return to_route('league.show', $career);
    }

    public function remove(Request $request, Career $career, CareerMembership $member, RemoveMember $remove): RedirectResponse
    {
        abort_unless($this->seat($career, $this->user($request))->isOwner(), 403);
        abort_unless($member->career_id === $career->id, 404);

        if (! $remove->handle($member)) {
            return to_route('league.show', $career)->withErrors([
                'member' => 'The owner cannot be removed from their own league.',
            ]);
        }

        return to_route('league.show', $career);
    }

    /** Hand in a team sheet, and play the round the moment it is the last one. */
    public function order(StoreOrderRequest $request, Career $career, SubmitOrder $submit, CurrentRound $currentRound, ResolveRound $resolve): RedirectResponse
    {
        $seat = $this->seat($career, $this->user($request));
        $round = $currentRound->handle($career);

        if (! $round instanceof LeagueRound) {
            return to_route('league.show', $career);
        }

        if ($submit->handle($round, $seat, $request->formation(), $request->mentality(), $request->isReady()) === null) {
            return to_route('league.show', $career)->withErrors([
                'order' => 'You need a club before you can name a team.',
            ]);
        }

        $resolve->handle($round);

        return to_route('league.show', $career);
    }

    /** How long the league waits on a manager before playing without them. */
    public function cadence(Request $request, Career $career): RedirectResponse
    {
        abort_unless($this->seat($career, $this->user($request))->isOwner(), 403);

        $hours = (int) $request->integer('hours');
        abort_unless($hours >= 1 && $hours <= 336, 422);

        $career->forceFill(['round_hours' => $hours])->save();

        return to_route('league.show', $career);
    }

    /**
     * @param  Collection<int, CareerMembership>  $memberships
     * @return array<string, mixed>
     */
    private function round(LeagueRound $round, CareerMembership $seat, Collection $memberships): array
    {
        $orders = $round->orders()->get()->keyBy('career_membership_id');
        $mine = $orders->get($seat->id);
        $names = $memberships->pluck('team.name', 'team_id');

        return [
            'matchday' => $round->matchday,
            'deadlineAt' => $round->deadline_at?->toIso8601String(),
            'waitingOn' => $memberships->whereNotNull('team_id')
                ->filter(fn (CareerMembership $m): bool => $orders->get($m->id)?->ready !== true)
                ->pluck('user.name')->values()->all(),
            'yourOrder' => $mine === null ? null : [
                'formation' => $mine->formation,
                'mentality' => $mine->mentality,
                'ready' => $mine->ready,
            ],
            'fixtures' => $round->season->fixtures()
                ->where('youth', false)->where('matchday', $round->matchday)
                ->with(['homeTeam', 'awayTeam'])->get()
                ->map(fn (Fixture $f): array => [
                    'id' => $f->id,
                    'home' => $f->homeTeam?->name,
                    'away' => $f->awayTeam?->name,
                    'homeGoals' => $f->home_goals,
                    'awayGoals' => $f->away_goals,
                    'played' => $f->played,
                    // A duel is the fixture two managers both have a stake in.
                    'duel' => $names->has($f->home_team_id) && $names->has($f->away_team_id),
                    'yours' => $seat->team_id !== null
                        && ($f->home_team_id === $seat->team_id || $f->away_team_id === $seat->team_id),
                ])->all(),
        ];
    }

    /** Only a seated manager sees a league, and only their own. */
    private function seat(Career $career, User $user): CareerMembership
    {
        abort_unless($career->isLeague(), 404);

        $seat = $career->seatFor($user);

        if (! $seat instanceof CareerMembership) {
            abort(403);
        }

        return $seat;
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
