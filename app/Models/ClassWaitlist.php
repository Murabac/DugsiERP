<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassWaitlist extends Model
{
    protected $table = 'class_waitlist';

    protected $fillable = [
        'student_id',
        'class_id',
        'academic_year',
        'position',
        'status',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => WaitlistStatus::class,
            'enrolled_at' => 'datetime',
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

    public function scopeWaiting($query)
    {
        return $query->where('status', WaitlistStatus::Waiting);
    }
}
