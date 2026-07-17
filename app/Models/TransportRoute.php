<?php

namespace App\Models;

use App\Enums\TransportAssignmentStatus;
use App\Enums\TransportRouteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportRoute extends Model
{
    protected $fillable = [
        'name',
        'code',
        'vehicle_id',
        'academic_year',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransportRouteStatus::class,
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TransportStop::class, 'route_id')->orderBy('sort_order')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TransportAssignment::class, 'route_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->where('status', TransportAssignmentStatus::Active);
    }

    public function capacity(): int
    {
        return (int) ($this->vehicle?->capacity ?? 0);
    }

    public function seatsUsed(): int
    {
        if (array_key_exists('active_assignments_count', $this->attributes)) {
            return (int) $this->attributes['active_assignments_count'];
        }

        return $this->activeAssignments()->count();
    }

    public function seatsFree(): int
    {
        return max(0, $this->capacity() - $this->seatsUsed());
    }

    public function isAtCapacity(): bool
    {
        return $this->seatsUsed() >= $this->capacity();
    }

    public function displayName(): string
    {
        $code = trim((string) ($this->code ?? ''));

        return $code !== '' ? $this->name.' ('.$code.')' : $this->name;
    }
}
