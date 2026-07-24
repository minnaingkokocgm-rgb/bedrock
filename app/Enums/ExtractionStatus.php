<?php

namespace App\Enums;

enum ExtractionStatus: string
{
    case Extracted = 'extracted';
    case S3Referenced = 's3_referenced';
    case Unsupported = 'unsupported';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Extracted => 'Extracted',
            self::S3Referenced => 'Referenced from S3',
            self::Unsupported => 'Unsupported type',
            self::Failed => 'Extraction failed',
        };
    }
}
