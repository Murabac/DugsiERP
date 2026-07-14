<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    protected $fillable = [
        'student_id',
        'class_id',
        'academic_year',
        'roll_number',
        'enrollment_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
            'roll_number' => 'integer',
            'status' => StudentStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
