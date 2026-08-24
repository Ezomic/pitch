<?php

declare(strict_types=1);

namespace App\Http\Requests\Career;

use App\Models\Career;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:60'],
            'type' => ['sometimes', Rule::in([Career::SOLO, Career::LEAGUE])],
        ];
    }

    public function careerName(): string
    {
        return trim($this->string('name')->toString());
    }

    public function careerType(): string
    {
        return $this->string('type', Career::SOLO)->toString();
    }
}
