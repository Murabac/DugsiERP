<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSlot extends Model
{
    protected $fillable = [
        'class_id',
        'academic_year',
        'day_of_week',
        'period_number',
        'start_time',
        'end_time',
        'subject_id',
        'teacher_id',
        'room',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_id');
    }

    public function timeLabel(): string
    {
        $start = substr((string) $this->start_time, 0, 5);
        $end = substr((string) $this->end_time, 0, 5);

        return "{$start}–{$end}";
    }
}
