<?php

namespace App\Http\Requests;

use App\Enums\CardSuit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlayCardRequest extends FormRequest
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
            'card_id' => ['required', 'integer', 'min:1'],
            'chosen_suit' => ['nullable', Rule::enum(CardSuit::class)],
            'state_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64', 'alpha_dash'],
        ];
    }
}
