<?php

namespace App\Enums;

enum TransportRouteStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    /** @return list<self> */
    public static function options(): array
    {
        return self::cases();
    }
}
