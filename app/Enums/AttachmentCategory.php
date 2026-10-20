<?php

namespace App\Enums;

enum AttachmentCategory: string
{
    case Endorsement = 'endorsement_letter';
    case Supporting = 'supporting_document';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Endorsement => 'Endorsement letter',
            self::Supporting => 'Supporting document',
            self::Other => 'Other',
        };
    }
}
