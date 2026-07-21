<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'employee_code',
        'full_name',
        'dob',
        'gender',
        'phone',
        'phones',
        'qualification',
        'subject_specialty',
        'work_days',
        'date_joined',
        'fixed_salary_usd',
        'role_label',
        'status',
        'checkin_token',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'date_joined' => 'date',
            'fixed_salary_usd' => 'decimal:2',
            'gender' => Gender::class,
            'status' => StaffStatus::class,
            'phones' => 'array',
            'work_days' => 'array',
        ];
    }

    /**
     * Trim, drop empties, de-dupe. First entry is the primary phone.
     *
     * @param  array<int, mixed>  $phones
     * @return list<string>
     */
    public static function normalizePhones(array $phones): array
    {
        $out = [];
        foreach ($phones as $phone) {
            $phone = trim((string) $phone);
            if ($phone === '' || in_array($phone, $out, true)) {
                continue;
            }
            $out[] = $phone;
        }

        return $out;
    }

    /**
     * Normalize teacher schedule to day => shifts map.
     * Accepts legacy flat day lists ["sat","mon"] (both shifts) or
     * ["sat" => ["first","second"], ...].
     *
     * @param  array<int|string, mixed>  $input
     * @return array<string, list<string>>
     */
    public static function normalizeWorkSchedule(array $input): array
    {
        $allowedDays = \App\Support\SchoolWeek::days();
        $allowedShifts = \App\Support\SchoolWeek::shifts();
        $out = [];

        if ($input !== [] && array_is_list($input)) {
            foreach ($input as $day) {
                $day = strtolower(trim((string) $day));
                if (in_array($day, $allowedDays, true)) {
                    $out[$day] = $allowedShifts;
                }
            }
        } else {
            foreach ($input as $day => $shifts) {
                $day = strtolower(trim((string) $day));
                if (! in_array($day, $allowedDays, true)) {
                    continue;
                }
                if (! is_array($shifts)) {
                    $shifts = filled($shifts) ? [$shifts] : [];
                }
                $norm = [];
                foreach ($shifts as $shift) {
                    $shift = strtolower(trim((string) $shift));
                    if (in_array($shift, $allowedShifts, true) && ! in_array($shift, $norm, true)) {
                        $norm[] = $shift;
                    }
                }
                $norm = array_values(array_filter($allowedShifts, fn (string $s) => in_array($s, $norm, true)));
                if ($norm !== []) {
                    $out[$day] = $norm;
                }
            }
        }

        $ordered = [];
        foreach ($allowedDays as $day) {
            if (isset($out[$day])) {
                $ordered[$day] = $out[$day];
            }
        }

        return $ordered;
    }

    /**
     * Full-week schedule: every school day, both shifts.
     *
     * @return array<string, list<string>>
     */
    public static function defaultWorkSchedule(): array
    {
        $schedule = [];
        foreach (\App\Support\SchoolWeek::days() as $day) {
            $schedule[$day] = \App\Support\SchoolWeek::shifts();
        }

        return $schedule;
    }

    /**
     * @deprecated Use normalizeWorkSchedule(); kept for day-key lists.
     *
     * @param  array<int, mixed>  $days
     * @return list<string>
     */
    public static function normalizeWorkDays(array $days): array
    {
        return array_keys(self::normalizeWorkSchedule($days));
    }

    /**
     * All contact numbers (JSON phones, falling back to legacy phone column).
     *
     * @return list<string>
     */
    public function phoneList(): array
    {
        $phones = is_array($this->phones) ? self::normalizePhones($this->phones) : [];
        if ($phones !== []) {
            return $phones;
        }

        return filled($this->phone) ? [(string) $this->phone] : [];
    }

    public function phonesDisplay(string $separator = ' · '): string
    {
        return implode($separator, $this->phoneList());
    }

    /**
     * Day => shifts map. Empty/null storage means full week, both shifts.
     *
     * @return array<string, list<string>>
     */
    public function workSchedule(): array
    {
        if (! is_array($this->work_days) || $this->work_days === []) {
            return self::defaultWorkSchedule();
        }

        $normalized = self::normalizeWorkSchedule($this->work_days);

        return $normalized !== [] ? $normalized : self::defaultWorkSchedule();
    }

    /**
     * Scheduled attend days.
     *
     * @return list<string>
     */
    public function workDayList(): array
    {
        return array_keys($this->workSchedule());
    }

    /**
     * @return list<string>
     */
    public function shiftsOn(string $dayKey): array
    {
        return $this->workSchedule()[$dayKey] ?? [];
    }

    public function worksOn(?string $dayKey = null): bool
    {
        $dayKey ??= \App\Support\SchoolWeek::dayKey();
        if ($dayKey === null) {
            return false;
        }

        return isset($this->workSchedule()[$dayKey]);
    }

    public function worksShift(string $dayKey, string $shift): bool
    {
        return in_array($shift, $this->shiftsOn($dayKey), true);
    }

    /** True if the teacher’s work schedule covers this day + period. */
    public function availableAt(string $dayKey, int $periodNumber): bool
    {
        $shift = \App\Support\SchoolWeek::shiftForPeriod($periodNumber);
        if ($shift === null) {
            return false;
        }

        return $this->worksShift($dayKey, $shift);
    }

    public function workDaysDisplay(string $separator = ', '): string
    {
        $parts = [];
        foreach ($this->workSchedule() as $day => $shifts) {
            $shiftBits = array_map(fn (string $s) => match ($s) {
                'first' => '1st',
                'second' => '2nd',
                default => $s,
            }, $shifts);
            $parts[] = \App\Support\SchoolWeek::dayLabel($day).' ('.implode('+', $shiftBits).')';
        }

        return implode($separator, $parts);
    }

    /**
     * @return list<string>
     */
    public function subjectNames(): array
    {
        if ($this->relationLoaded('subjectAssignments')) {
            $names = $this->subjectAssignments
                ->map(fn (TeacherSubjectAssignment $a) => $a->subject?->name)
                ->filter()
                ->unique()
                ->values()
                ->all();
        } else {
            $names = $this->subjectAssignments()
                ->with('subject')
                ->get()
                ->pluck('subject.name')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if ($names !== []) {
            return array_values($names);
        }

        return filled($this->subject_specialty) ? [(string) $this->subject_specialty] : [];
    }

    public function subjectsDisplay(string $separator = ', '): string
    {
        return implode($separator, $this->subjectNames());
    }

    /**
     * Store role keys as strings (system roles table + legacy librarian/driver).
     */
    public function setRoleLabelAttribute(mixed $value): void
    {
        $this->attributes['role_label'] = $value instanceof \BackedEnum ? $value->value : $value;
    }

    protected static function booted(): void
    {
        static::creating(function (Staff $staff) {
            if (blank($staff->checkin_token)) {
                $staff->checkin_token = static::generateCheckinToken();
            }
        });
    }

    public static function generateCheckinToken(): string
    {
        return \Illuminate\Support\Str::random(48);
    }

    public function checkinUrl(): string
    {
        return route('staff-checkin.show', ['token' => $this->checkin_token]);
    }

    public function regenerateCheckinToken(): void
    {
        $this->forceFill(['checkin_token' => static::generateCheckinToken()])->save();
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'staff_id');
    }

    public function subjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class, 'staff_id');
    }

    public function assignedClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'staff_class_assignments', 'staff_id', 'class_id')
            ->withTimestamps();
    }

    public function accessRole(): ?Role
    {
        return Role::query()->where('key', $this->roleKey())->first();
    }

    public function roleKey(): string
    {
        $value = $this->attributes['role_label'] ?? '';

        return $value instanceof \BackedEnum ? $value->value : (string) $value;
    }

    public function roleDisplayName(): string
    {
        if ($role = $this->accessRole()) {
            return $role->name;
        }

        $legacy = StaffRoleLabel::tryFrom($this->roleKey());

        return $legacy?->label() ?? Str::headline($this->roleKey());
    }

    public function isTeacherRole(): bool
    {
        return $this->roleKey() === StaffRoleLabel::Teacher->value;
    }

    /**
     * May be assigned as Form Master (class head) — teachers and the form_master role.
     */
    public function canBeFormMaster(): bool
    {
        if ($this->status !== StaffStatus::Active) {
            return false;
        }

        return in_array($this->roleKey(), [
            StaffRoleLabel::Teacher->value,
            'form_master',
        ], true);
    }

    /**
     * Active staff eligible to head a class (Form Master dropdown).
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function queryEligibleFormMasters()
    {
        return static::query()
            ->where('status', StaffStatus::Active)
            ->whereIn('role_label', [
                StaffRoleLabel::Teacher->value,
                'form_master',
            ]);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(StaffAttendanceRecord::class);
    }

    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(StaffWebauthnCredential::class);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];
        $first = mb_substr($parts[0] ?? 'S', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

        return mb_strtoupper($first.($last !== $first ? $last : ''));
    }

    public static function nextEmployeeCode(): string
    {
        static::query()->orderByDesc('id')->lockForUpdate()->first();

        $latest = static::query()
            ->where('employee_code', 'like', 'EMP-%')
            ->orderByDesc('id')
            ->value('employee_code');

        $num = 1;
        if ($latest && preg_match('/EMP-(\d+)/', $latest, $m)) {
            $num = ((int) $m[1]) + 1;
        }

        return 'EMP-'.str_pad((string) $num, 3, '0', STR_PAD_LEFT);
    }
}
