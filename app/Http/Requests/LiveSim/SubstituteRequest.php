<?php

declare(strict_types=1);

namespace App\Http\Requests\LiveSim;

use App\Actions\LiveSim\Substitute;
use App\Models\LiveMatch;
use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubstituteRequest extends FormRequest
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
            'out_slot' => ['required', 'integer', 'min:1', 'max:10'],
            'player_id' => ['required', 'integer'],
        ];
    }

    /**
     * The checks that need the match itself. Only ownership was ever verified,
     * so the same player could be fielded twice, or brought on when there were
     * no substitutions left.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $match = $this->route('match');

                if (! $match instanceof LiveMatch) {
                    return;
                }

                if ($match->subs_remaining <= 0) {
                    $validator->errors()->add('player_id', 'No substitutions left.');

                    return;
                }

                // authorize() has already matched the match to this user, so by
                // the time these run there is one.
                $player = Player::query()->selectableFor($this->user()->id)
                    ->find($this->integer('player_id'));

                if (! $player instanceof Player) {
                    $validator->errors()->add('player_id', 'That player cannot be selected.');

                    return;
                }

                if ((new Substitute)->onPitch($match, $player)) {
                    $validator->errors()->add('player_id', 'That player is already on the pitch.');
                }
            },
        ];
    }
}
