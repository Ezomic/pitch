<?php

declare(strict_types=1);

namespace App\Http\Requests\League;

use App\Sim\Engine\Formation;
use App\Sim\Engine\Mentality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'formation' => ['required', Rule::in(array_keys(Formation::all()))],
            'mentality' => ['required', Rule::in(array_column(Mentality::cases(), 'value'))],
            'ready' => ['required', 'boolean'],
        ];
    }

    public function formation(): string
    {
        return $this->string('formation')->toString();
    }

    public function mentality(): string
    {
        return $this->string('mentality')->toString();
    }

    public function isReady(): bool
    {
        return $this->boolean('ready');
    }
}
