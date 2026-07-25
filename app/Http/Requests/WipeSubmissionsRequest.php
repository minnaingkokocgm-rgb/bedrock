<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WipeSubmissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'in:WIPE'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.in' => 'Type WIPE exactly to confirm wiping all submissions.',
        ];
    }
}
