<?php

namespace App\Enum;

enum ResumeKind: string
{
    case File = 'file';
    case Link = 'link';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
