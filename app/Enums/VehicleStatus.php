<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Maintenance => 'Maintenance',
            self::Retired => 'Retired',
        };
    }

    /** @return list<self> */
    public static function options(): array
    {
        return self::cases();
    }
}
