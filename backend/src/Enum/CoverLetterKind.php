<?php

namespace App\Enum;

enum CoverLetterKind: string
{
    case Text = 'text';
    case Link = 'link';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
