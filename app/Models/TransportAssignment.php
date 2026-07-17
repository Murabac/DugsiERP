<?php

namespace App\Models;

use App\Enums\TransportAssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportAssignment extends Model
{
    protected $fillable = [
        'student_id',
        'route_id',
        'stop_id',
        'academic_year',
        'status',
        'started_on',
        'ended_on',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransportAssignmentStatus::class,
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(TransportStop::class, 'stop_id');
    }

    public function isActive(): bool
    {
        return $this->status === TransportAssignmentStatus::Active;
    }
}
