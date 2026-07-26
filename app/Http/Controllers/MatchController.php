<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\SimulateMatch;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MatchController extends Controller
{
    public function show(Request $request, EnsureSquad $ensureSquad, SimulateMatch $simulateMatch): Response
    {
        $seed = max(1, (int) $request->integer('seed', 1));
        $squad = $ensureSquad->handle($this->user($request));

        return Inertia::render('Match', [
            'seed' => $seed,
            'report' => $simulateMatch->handle($squad, $seed, positional: true)->toArray(),
        ]);
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
