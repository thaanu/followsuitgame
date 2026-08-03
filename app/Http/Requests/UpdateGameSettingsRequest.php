<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesGameSettings;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGameSettingsRequest extends FormRequest
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
        return $this->settingsRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->settingsMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normaliseEffects($this->all()));

        if ($this->has('name')) {
            $this->merge(['name' => trim(strip_tags((string) $this->input('name'))) ?: null]);
        }
    }
}
