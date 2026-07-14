<?php

namespace App\Support;

class Subjects
{
    /**
     * Fixed secondary subject catalog (CONTEXT.md).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'Somali Language',
            'Arabic Language',
            'English',
            'Mathematics',
            'Islamic Studies',
            'Geography',
            'History',
            'Physics',
            'Chemistry',
            'Biology',
        ];
    }
}
