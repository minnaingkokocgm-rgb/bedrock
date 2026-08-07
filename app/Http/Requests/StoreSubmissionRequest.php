<?php

namespace App\Http\Requests;

use App\Enums\SubmissionSource;
use App\Enums\SubmissionType;
use App\Support\S3Uri;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use InvalidArgumentException;
use Throwable;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('source')) {
            $this->merge(['source' => SubmissionSource::Upload->value]);
        }
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
            'source' => ['required', Rule::enum(SubmissionSource::class)],
            'file' => [
                'exclude_unless:source,upload',
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
            's3_uri' => [
                'exclude_unless:source,s3_uri',
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $configuredBucket = (string) config('filesystems.disks.s3.bucket');

                    if ($configuredBucket === '') {
                        $fail('AWS_BUCKET must be configured to submit an S3 URI.');

                        return;
                    }

                    try {
                        $uri = S3Uri::parse($value);
                    } catch (InvalidArgumentException $e) {
                        $fail($e->getMessage());

                        return;
                    }

                    if ($uri->bucket !== $configuredBucket) {
                        $fail("The S3 URI bucket must be \"{$configuredBucket}\".");

                        return;
                    }

                    $type = SubmissionType::fromExtension($uri->extension());

                    if ($type === null) {
                        $fail('The S3 object file type is not supported.');

                        return;
                    }

                    try {
                        if (! Storage::disk('s3')->exists($uri->key)) {
                            $fail('The S3 object was not found in the configured bucket.');
                        }
                    } catch (Throwable $e) {
                        $fail('Unable to verify the S3 object: '.$e->getMessage());
                    }
                },
            ],
        ];
    }
}
