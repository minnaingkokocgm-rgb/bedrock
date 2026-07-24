<?php

namespace App\Services;

use App\Enums\SubmissionType;
use App\Models\Submission;
use Illuminate\Support\Str;

class BedrockContentMapper
{
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

        $extension = strtolower(pathinfo($submission->original_filename, PATHINFO_EXTENSION));
        $name = Str::of(pathinfo($submission->original_filename, PATHINFO_FILENAME))
            ->slug('_')
            ->limit(50, '')
            ->toString() ?: 'submission';

        return match ($submission->type) {
            SubmissionType::Document => $this->documentBlock($extension, $name, $uri),
            SubmissionType::Image => $this->imageBlock($extension, $uri),
            SubmissionType::Video => $this->videoBlock($extension, $uri),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function documentBlock(string $extension, string $name, string $uri): ?array
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
                'source' => [
                    's3Location' => [
                        'uri' => $uri,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function imageBlock(string $extension, string $uri): ?array
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
                'source' => [
                    's3Location' => [
                        'uri' => $uri,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function videoBlock(string $extension, string $uri): ?array
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
                'source' => [
                    's3Location' => [
                        'uri' => $uri,
                    ],
                ],
            ],
        ];
    }
}
