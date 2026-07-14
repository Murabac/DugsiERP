<?php

namespace App\Enums;

enum GuardianRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Sibling = 'sibling';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::Uncle => 'Uncle',
            self::Aunt => 'Aunt',
            self::Sibling => 'Sibling',
            self::Other => 'Other',
        };
    }
}
