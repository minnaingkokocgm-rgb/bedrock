<?php

namespace App\Http\Requests;

use App\Enums\SubmissionType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'submitter_name' => ['required', 'string', 'max:255'],
            'submitter_email' => ['required', 'email', 'max:255'],
            'file' => [
                'required',
                File::types(SubmissionType::allowedExtensions())->max(100 * 1024),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $type = SubmissionType::fromUploadedFile($value);

                    if ($type === null) {
                        $fail('The uploaded file type is not supported.');

                        return;
                    }

                    $maxKilobytes = match ($type) {
                        SubmissionType::Document => 20 * 1024,
                        SubmissionType::Image => 10 * 1024,
                        SubmissionType::Video => 100 * 1024,
                    };

                    if (($value->getSize() ?: 0) > $maxKilobytes * 1024) {
                        $fail("The {$type->value} may not be greater than ".($maxKilobytes / 1024).'MB.');
                    }
                },
            ],
        ];
    }
}
