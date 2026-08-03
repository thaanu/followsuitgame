<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveTurnRequest extends FormRequest
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
            'state_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64', 'alpha_dash'],
        ];
    }
}
