<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinGameRequest extends FormRequest
{
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
            'token' => ['nullable', 'string', 'max:64'],
            'player_token' => ['nullable', 'string', 'size:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.regex' => 'Nicknames may only contain letters, numbers, spaces and - _ . \'',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nickname')) {
            $this->merge(['nickname' => trim(strip_tags((string) $this->input('nickname')))]);
        }
    }
}
