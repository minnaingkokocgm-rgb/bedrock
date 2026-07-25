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
        $maxDocumentKb = (int) config('submissions.max_kilobytes.document', 20 * 1024);
        $maxImageKb = (int) config('submissions.max_kilobytes.image', 10 * 1024);
        $maxVideoKb = (int) config('submissions.max_kilobytes.video', 1024 * 1024);
        $absoluteMaxKb = max($maxDocumentKb, $maxImageKb, $maxVideoKb);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'submitter_name' => ['required', 'string', 'max:255'],
            'submitter_email' => ['required', 'email', 'max:255'],
            'file' => [
                'required',
                // Validate by extension (not MIME). Windows often reports .wmv as video/x-ms-asf,
                // which fails File::types() even though .wmv is allowed.
                File::default()
                    ->extensions(SubmissionType::allowedExtensions())
                    ->max($absoluteMaxKb),
                function (string $attribute, mixed $value, Closure $fail) use ($maxDocumentKb, $maxImageKb, $maxVideoKb): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $type = SubmissionType::fromUploadedFile($value);

                    if ($type === null) {
                        $fail('The uploaded file type is not supported.');

                        return;
                    }

                    $maxKilobytes = match ($type) {
                        SubmissionType::Document => $maxDocumentKb,
                        SubmissionType::Image => $maxImageKb,
                        SubmissionType::Video => $maxVideoKb,
                    };

                    if (($value->getSize() ?: 0) > $maxKilobytes * 1024) {
                        $fail("The {$type->value} may not be greater than ".($maxKilobytes / 1024).'MB.');
                    }
                },
            ],
        ];
    }
}
