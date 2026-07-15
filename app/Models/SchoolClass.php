<?php

namespace App\Models;

use App\Enums\ClassStatus;
use App\Enums\WaitlistStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'form_level',
        'section',
        'academic_year',
        'capacity',
        'room',
        'homeroom_teacher_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'form_level' => 'integer',
            'capacity' => 'integer',
            'status' => ClassStatus::class,
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'homeroom_teacher_id');
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(ClassWaitlist::class, 'class_id');
    }

    public function waitingList(): HasMany
    {
        return $this->waitlistEntries()
            ->where('status', WaitlistStatus::Waiting)
            ->orderBy('position');
    }

    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->where('status', 'active');
    }

    public function displayName(): string
    {
        return "Form {$this->form_level} - {$this->section}";
    }

    public function classroom(): string
    {
        if (filled($this->room)) {
            return $this->room;
        }

        return 'R-'.$this->form_level.strtoupper((string) $this->section);
    }

    public function enrolledCount(): int
    {
        return $this->activeEnrollments()->count();
    }

    public function fillPercent(): int
    {
        if ($this->capacity <= 0) {
            return 0;
        }

        return (int) round(($this->enrolledCount() / $this->capacity) * 100);
    }

    /**
     * Next roll for this class/year. Call after locking the class row in a transaction.
     */
    public function nextRollNumber(): int
    {
        $max = $this->enrollments()
            ->where('academic_year', $this->academic_year)
            ->lockForUpdate()
            ->max('roll_number');

        return ((int) $max) + 1;
    }

    public function isFull(): bool
    {
        return $this->enrolledCount() >= $this->capacity;
    }

    public function openSeats(): int
    {
        return max(0, $this->capacity - $this->enrolledCount());
    }

    public function nextWaitlistPosition(): int
    {
        $max = $this->waitlistEntries()
            ->where('academic_year', $this->academic_year)
            ->lockForUpdate()
            ->max('position');

        return ((int) $max) + 1;
    }
}
