<?php

declare(strict_types=1);

namespace App\Http\Requests\LiveSim;

use App\Models\LiveMatch;
use App\Sim\Engine\Mentality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetMentalityRequest extends FormRequest
{
    /** Ownership is checked here so it runs before validation, not after it. */
    public function authorize(): bool
    {
        $match = $this->route('match');

        return $match instanceof LiveMatch && $match->user_id === $this->user()?->id;
    }

    /**
     * Mentality is a backed enum, so validate against it. This used to accept
     * any string at all: the action quietly ignored anything it did not know,
     * while the response still echoed the requested value, so the interface
     * showed a tactical change that never happened.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mentality' => ['required', Rule::enum(Mentality::class)],
        ];
    }

    public function mentality(): Mentality
    {
        return Mentality::from($this->string('mentality')->toString());
    }
}
