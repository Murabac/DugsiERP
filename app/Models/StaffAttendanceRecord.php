<?php

namespace App\Models;

use App\Enums\StaffAttendanceSource;
use App\Enums\StaffAttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendanceRecord extends Model
{
    protected $fillable = [
        'staff_id',
        'date',
        'status',
        'check_in_at',
        'check_out_at',
        'source',
        'marked_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => StaffAttendanceStatus::class,
            'source' => StaffAttendanceSource::class,
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
