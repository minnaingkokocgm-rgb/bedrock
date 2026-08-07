<?php

namespace App\Enums;

enum SubmissionSource: string
{
    case Upload = 'upload';
    case S3Uri = 's3_uri';

    public function label(): string
    {
        return match ($this) {
            self::Upload => 'Upload',
            self::S3Uri => 'S3 URI',
        };
    }

    public function ownsStoredFile(): bool
    {
        return $this === self::Upload;
    }
}
