<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Squad\EnsureSquad;
use App\Actions\Squad\RenewContract;
use App\Actions\Squad\SellPlayer;
use App\Actions\Squad\SignPlayer;
use App\Models\Player;
use App\Models\Squad;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(Request $request, EnsureSquad $ensureSquad): Response
    {
        $squad = $ensureSquad->handle($this->user($request));

        $wageBill = $squad->wageBill();

        return Inertia::render('Transfers', [
            'bank' => $squad->bank,
            'finances' => [
                'income' => $squad->weekly_income,
                'wageBill' => $wageBill,
                'net' => $squad->weekly_income - $wageBill,
            ],
            'market' => Player::query()->where('is_free_agent', true)
                ->orderByDesc('vision')->orderBy('name')->get()
                ->map(fn (Player $player) => $this->card($player, $squad))->all(),
            'owned' => Player::query()->where('user_id', $squad->user_id)->where('is_youth', false)
                ->orderBy('name')->get()
                ->map(fn (Player $player) => $this->card($player, $squad))->all(),
        ]);
    }

    public function sign(Request $request, Player $player, EnsureSquad $ensureSquad, SignPlayer $signPlayer): RedirectResponse
    {
        $signPlayer->handle($ensureSquad->handle($this->user($request)), $player);

        return to_route('transfers.index');
    }

    public function sell(Request $request, Player $player, EnsureSquad $ensureSquad, SellPlayer $sellPlayer): RedirectResponse
    {
        $sellPlayer->handle($ensureSquad->handle($this->user($request)), $player);

        return to_route('transfers.index');
    }

    public function renew(Request $request, Player $player, EnsureSquad $ensureSquad, RenewContract $renewContract): RedirectResponse
    {
        $renewContract->handle($ensureSquad->handle($this->user($request)), $player);

        return to_route('transfers.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Player $player, Squad $squad): array
    {
        return [
            'id' => $player->id,
            'name' => $player->name,
            'position' => $player->position->value,
            'age' => $player->age,
            'overall' => $player->overall(),
            'value' => $player->value(),
            'wage' => $player->weeklyWage(),
            'contractYears' => $player->contract_years,
            'affordable' => $squad->bank >= $player->value(),
        ];
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
