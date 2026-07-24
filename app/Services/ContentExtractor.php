<?php

namespace App\Services;

use App\Enums\ExtractionStatus;
use App\Models\Submission;
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

        if (! in_array($extension, ['txt', 'csv', 'pdf'], true)) {
            return [
                'status' => ExtractionStatus::Unsupported,
                'content' => null,
                'error' => "Text extraction is not available for .{$extension} files in v1.",
            ];
        }

        $disk = $submission->disk ?: 'local';

        try {
            $exists = Storage::disk($disk)->exists($submission->disk_path);
        } catch (Throwable) {
            $exists = false;
        }

        if (! $exists) {
            return [
                'status' => ExtractionStatus::Failed,
                'content' => null,
                'error' => 'Stored file was not found.',
            ];
        }

        try {
            if ($disk === 's3') {
                $contents = Storage::disk('s3')->get($submission->disk_path);

                if (! is_string($contents) || $contents === '') {
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

                $content = trim(match ($extension) {
                    'txt', 'csv' => $this->extractPlainText($absolutePath),
                    'pdf' => $this->extractPdf($absolutePath),
                });
            }

            if ($content === '') {
                return [
                    'status' => ExtractionStatus::Failed,
                    'content' => null,
                    'error' => 'No extractable text was found in the file.',
                ];
            }

            return [
                'status' => ExtractionStatus::Extracted,
                'content' => $content,
                'error' => null,
            ];
        } catch (Throwable $e) {
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
