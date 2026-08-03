<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesGameSettings;
use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
{
    use ValidatesGameSettings;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\p{L}\p{N} _.\'-]+$/u'],
            ...$this->settingsRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.regex' => 'Nicknames may only contain letters, numbers, spaces and - _ . \'',
            ...$this->settingsMessages(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normaliseEffects($this->all()));

        if ($this->has('nickname')) {
            $this->merge(['nickname' => trim(strip_tags((string) $this->input('nickname')))]);
        }

        if ($this->has('name')) {
            $this->merge(['name' => trim(strip_tags((string) $this->input('name'))) ?: null]);
        }
    }
}
