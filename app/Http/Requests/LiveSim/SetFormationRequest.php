<?php

declare(strict_types=1);

namespace App\Http\Requests\LiveSim;

use App\Models\LiveMatch;
use App\Sim\Engine\Formation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetFormationRequest extends FormRequest
{
    /** Ownership is checked here so it runs before validation, not after it. */
    public function authorize(): bool
    {
        $match = $this->route('match');

        return $match instanceof LiveMatch && $match->user_id === $this->user()?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'formation' => ['required', 'string', Rule::in(array_keys(Formation::all()))],
        ];
    }

    public function formation(): Formation
    {
        return Formation::fromId($this->string('formation')->toString());
    }
}
