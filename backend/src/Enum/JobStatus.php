<?php

namespace App\Enum;

enum JobStatus: string
{
    case Investigating = 'investigating';
    case Applied = 'applied';
    case InProgress = 'in_progress';
    case NoResponse = 'no_response';
    case Rejected = 'rejected';
    case Accepted = 'accepted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
