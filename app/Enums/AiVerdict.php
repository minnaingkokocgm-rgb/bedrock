<?php

namespace App\Enums;

enum AiVerdict: string
{
    case Accept = 'accept';
    case Reject = 'reject';
    case Inconclusive = 'inconclusive';

    public function label(): string
    {
        return match ($this) {
            self::Accept => 'Recommend accept',
            self::Reject => 'Recommend reject',
            self::Inconclusive => 'Inconclusive',
        };
    }
}
