<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\WaitlistStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    protected $fillable = [
        'student_code',
        'full_name',
        'dob',
        'gender',
        'photo_path',
        'address',
        'city',
        'previous_school',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'gender' => Gender::class,
            'status' => StudentStatus::class,
        ];
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function primaryGuardian(): HasOne
    {
        return $this->hasOne(Guardian::class)->where('is_primary', true);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(ClassWaitlist::class);
    }

    public function activeWaitlistEntry(): HasOne
    {
        return $this->hasOne(ClassWaitlist::class)
            ->where('status', WaitlistStatus::Waiting)
            ->latestOfMany();
    }

    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->latestOfMany();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];
        $first = mb_substr($parts[0] ?? 'S', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

        return mb_strtoupper($first.($last !== $first ? $last : ''));
    }

    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return asset('storage/'.$this->photo_path);
    }

    /**
     * Next STU-### code. Call inside a DB transaction so lockForUpdate serializes callers.
     */
    public static function nextStudentCode(): string
    {
        static::query()->orderByDesc('id')->lockForUpdate()->first();

        $latest = static::query()
            ->where('student_code', 'like', 'STU-%')
            ->orderByDesc('id')
            ->value('student_code');

        $num = 1;
        if ($latest && preg_match('/STU-(\d+)/', $latest, $m)) {
            $num = ((int) $m[1]) + 1;
        }

        return 'STU-'.str_pad((string) $num, 3, '0', STR_PAD_LEFT);
    }
}
