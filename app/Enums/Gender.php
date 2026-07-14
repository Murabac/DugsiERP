<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Male => 'M',
            self::Female => 'F',
        };
    }
}
