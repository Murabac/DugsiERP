<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StaffRoleLabel;
use App\Enums\StaffStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'employee_code',
        'full_name',
        'dob',
        'gender',
        'phone',
        'qualification',
        'subject_specialty',
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
            'role_label' => StaffRoleLabel::class,
            'status' => StaffStatus::class,
        ];
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
