<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Career\ClaimClub;
use App\Actions\Career\InviteToLeague;
use App\Actions\Career\JoinLeague;
use App\Actions\Career\RemoveMember;
use App\Models\Career;
use App\Models\CareerInvitation;
use App\Models\CareerMembership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeagueController extends Controller
{
    /** The lobby: who is in, which club they run, and what is still free. */
    public function show(Request $request, Career $career): Response
    {
        $user = $this->user($request);
        $seat = $this->seat($career, $user);

        $memberships = $career->memberships()->with(['user', 'team'])->orderBy('id')->get();
        $taken = $memberships->pluck('team_id')->filter()->all();

        return Inertia::render('League', [
            'career' => ['id' => $career->id, 'name' => $career->name],
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
