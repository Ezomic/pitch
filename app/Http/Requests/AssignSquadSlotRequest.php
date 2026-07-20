<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Sim\Engine\Roster;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSquadSlotRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slot' => ['required', 'integer', Rule::in(Roster::slots())],
            'player_id' => ['required', 'integer', 'exists:players,id'],
        ];
    }

    public function slot(): int
    {
        return (int) $this->integer('slot');
    }

    public function playerId(): int
    {
        return (int) $this->integer('player_id');
    }
}
