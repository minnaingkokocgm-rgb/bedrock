<?php

namespace App\Services;

use App\Enums\ExtractionStatus;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

class ContentExtractor
{
    /**
     * @return array{status: ExtractionStatus, content: ?string, error: ?string}
     */
    public function extract(Submission $submission): array
    {
        $extension = strtolower(pathinfo($submission->original_filename, PATHINFO_EXTENSION));
        $disk = $submission->disk ?: 'local';
        $context = [
            'submission_id' => $submission->id,
            'disk' => $disk,
            'disk_path' => $submission->disk_path,
            'original_filename' => $submission->original_filename,
            'extension' => $extension,
        ];

        if (! in_array($extension, ['txt', 'csv', 'pdf'], true)) {
            Log::info('submission.extract.unsupported', $context);

            return [
                'status' => ExtractionStatus::Unsupported,
                'content' => null,
                'error' => "Text extraction is not available for .{$extension} files in v1.",
            ];
        }

        try {
            $exists = Storage::disk($disk)->exists($submission->disk_path);
        } catch (Throwable $e) {
            Log::error('submission.extract.storage_check_failed', [
                ...$context,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => ExtractionStatus::Failed,
                'content' => null,
                'error' => 'Storage check failed ('.$disk.'): '.$e->getMessage(),
            ];
        }

        if (! $exists) {
            $absoluteHint = $disk === 'local'
                ? storage_path('app/private/'.$submission->disk_path)
                : null;

            Log::warning('submission.extract.file_missing', [
                ...$context,
                'absolute_hint' => $absoluteHint,
                'bucket' => $disk === 's3' ? config('filesystems.disks.s3.bucket') : null,
            ]);

            return [
                'status' => ExtractionStatus::Failed,
                'content' => null,
                'error' => 'Stored file was not found.',
            ];
        }

        try {
            if ($disk === 's3') {
                Log::info('submission.extract.s3_read_start', $context);

                $contents = Storage::disk('s3')->get($submission->disk_path);

                if (! is_string($contents) || $contents === '') {
                    Log::warning('submission.extract.s3_empty', $context);

                    return [
                        'status' => ExtractionStatus::Failed,
                        'content' => null,
                        'error' => 'No extractable text was found in the file.',
                    ];
                }

                if ($extension === 'pdf') {
                    $temporaryPath = tempnam(sys_get_temp_dir(), 'submission-pdf-');

                    if ($temporaryPath === false) {
                        throw new \RuntimeException('Unable to create a temporary file for PDF extraction.');
                    }

                    file_put_contents($temporaryPath, $contents);

                    try {
                        $content = trim($this->extractPdf($temporaryPath));
                    } finally {
                        @unlink($temporaryPath);
                    }
                } else {
                    $content = trim($contents);
                }
            } else {
                $absolutePath = Storage::disk($disk)->path($submission->disk_path);
                Log::info('submission.extract.local_read_start', [
                    ...$context,
                    'absolute_path' => $absolutePath,
                ]);

                $content = trim(match ($extension) {
                    'txt', 'csv' => $this->extractPlainText($absolutePath),
                    'pdf' => $this->extractPdf($absolutePath),
                });
            }

            if ($content === '') {
                Log::warning('submission.extract.empty_text', $context);

                return [
                    'status' => ExtractionStatus::Failed,
                    'content' => null,
                    'error' => 'No extractable text was found in the file.',
                ];
            }

            Log::info('submission.extract.success', [
                ...$context,
                'content_length' => mb_strlen($content),
            ]);

            return [
                'status' => ExtractionStatus::Extracted,
                'content' => $content,
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('submission.extract.read_failed', [
                ...$context,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => ExtractionStatus::Failed,
                'content' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function extractPlainText(string $absolutePath): string
    {
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read plain text file.');
        }

        return $contents;
    }

    private function extractPdf(string $absolutePath): string
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($absolutePath);

        return $pdf->getText();
    }
}
