<?php

namespace App\Enums;

enum AcademicTerm: string
{
    case Term1 = 'Term 1';
    case Term2 = 'Term 2';
    case Term3 = 'Term 3';
    case FinalExam = 'Final Exam';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return list<self>
     */
    public static function options(): array
    {
        return self::cases();
    }
}
