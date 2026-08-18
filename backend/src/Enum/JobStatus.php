<?php

namespace App\Enum;

enum JobStatus: string
{
    case Investigating = 'investigating';
    case Applied = 'applied';
    case InProgress = 'in_progress';
    case Rejected = 'rejected';
    case Accepted = 'accepted';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
