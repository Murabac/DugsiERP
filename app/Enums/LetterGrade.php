<?php

namespace App\Enums;

enum LetterGrade: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case F = 'F';

    public function label(): string
    {
        return $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::A => 'bg-green-100 text-green-800',
            self::B => 'bg-blue-100 text-blue-800',
            self::C => 'bg-amber-100 text-amber-800',
            self::D => 'bg-slate-100 text-slate-700',
            self::F => 'bg-red-100 text-red-800',
        };
    }
}
