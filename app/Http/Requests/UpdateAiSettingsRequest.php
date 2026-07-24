<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiSettingsRequest extends FormRequest
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
            'system_prompt' => ['required', 'string', 'max:20000'],
            'document_rules' => ['required', 'string', 'max:20000'],
            'image_rules' => ['required', 'string', 'max:20000'],
            'video_rules' => ['required', 'string', 'max:20000'],
        ];
    }
}
