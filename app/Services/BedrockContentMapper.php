<?php

namespace App\Services;

use App\Enums\SubmissionType;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BedrockContentMapper
{
    /**
     * Only Amazon Nova models support document/image/video s3Location in Converse.
     */
    public function supportsS3Location(?string $modelId = null): bool
    {
        $modelId ??= (string) config('services.bedrock.model_id');

        return str_contains(strtolower($modelId), 'amazon.nova');
    }

    /**
     * Map a submission to a Bedrock Converse content block.
     * Nova → s3Location; other models → downloaded bytes.
     *
     * @return array<string, mixed>|null
     */
    public function contentBlock(Submission $submission, ?string $modelId = null): ?array
    {
        if (! $submission->usesS3()) {
            return null;
        }

        if ($this->supportsS3Location($modelId)) {
            return $this->s3ContentBlock($submission);
        }

        // Claude and other non-Nova models do not accept video via Converse document/image APIs.
        if ($submission->type === SubmissionType::Video) {
            return null;
        }

        return $this->bytesContentBlock($submission);
    }

    /**
     * Map a submission to a Bedrock Converse content block that uses s3Location.
     *
     * @return array<string, mixed>|null
     */
    public function s3ContentBlock(Submission $submission): ?array
    {
        $uri = $submission->s3Uri();

        if ($uri === null) {
            return null;
        }

        return $this->buildBlock($submission, [
            's3Location' => [
                'uri' => $uri,
            ],
        ]);
    }

    /**
     * Map a submission to a Bedrock Converse content block that uses raw bytes.
     *
     * @return array<string, mixed>|null
     */
    public function bytesContentBlock(Submission $submission): ?array
    {
        try {
            $bytes = Storage::disk($submission->disk)->get($submission->disk_path);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Unable to read submission file for Bedrock bytes upload: '.$e->getMessage(),
                0,
                $e,
            );
        }

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return $this->buildBlock($submission, [
            'bytes' => $bytes,
        ]);
    }

    /**
     * @param  array{s3Location?: array{uri: string}, bytes?: string}  $source
     * @return array<string, mixed>|null
     */
    private function buildBlock(Submission $submission, array $source): ?array
    {
        $extension = strtolower(pathinfo($submission->original_filename, PATHINFO_EXTENSION));
        $name = Str::of(pathinfo($submission->original_filename, PATHINFO_FILENAME))
            ->slug('_')
            ->limit(50, '')
            ->toString() ?: 'submission';

        return match ($submission->type) {
            SubmissionType::Document => $this->documentBlock($extension, $name, $source),
            SubmissionType::Image => $this->imageBlock($extension, $source),
            SubmissionType::Video => $this->videoBlock($extension, $source),
        };
    }

    /**
     * @param  array{s3Location?: array{uri: string}, bytes?: string}  $source
     * @return array<string, mixed>|null
     */
    private function documentBlock(string $extension, string $name, array $source): ?array
    {
        $format = match ($extension) {
            'pdf' => 'pdf',
            'csv' => 'csv',
            'doc' => 'doc',
            'docx' => 'docx',
            'xls' => 'xls',
            'xlsx' => 'xlsx',
            'html', 'htm' => 'html',
            'txt' => 'txt',
            'md', 'markdown' => 'md',
            default => null,
        };

        if ($format === null) {
            return null;
        }

        return [
            'document' => [
                'format' => $format,
                'name' => $name,
                'source' => $source,
            ],
        ];
    }

    /**
     * @param  array{s3Location?: array{uri: string}, bytes?: string}  $source
     * @return array<string, mixed>|null
     */
    private function imageBlock(string $extension, array $source): ?array
    {
        $format = match ($extension) {
            'png' => 'png',
            'jpg', 'jpeg' => 'jpeg',
            'gif' => 'gif',
            'webp' => 'webp',
            default => null,
        };

        if ($format === null) {
            return null;
        }

        return [
            'image' => [
                'format' => $format,
                'source' => $source,
            ],
        ];
    }

    /**
     * @param  array{s3Location?: array{uri: string}, bytes?: string}  $source
     * @return array<string, mixed>|null
     */
    private function videoBlock(string $extension, array $source): ?array
    {
        $format = match ($extension) {
            'mp4' => 'mp4',
            'mov' => 'mov',
            'mkv' => 'mkv',
            'webm' => 'webm',
            'flv' => 'flv',
            'mpeg', 'mpg' => 'mpeg',
            'wmv' => 'wmv',
            '3gp' => 'three_gp',
            default => null,
        };

        if ($format === null) {
            return null;
        }

        return [
            'video' => [
                'format' => $format,
                'source' => $source,
            ],
        ];
    }
}
