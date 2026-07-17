<?php

namespace App\Models;

use App\Enums\TransportRouteStatus;
use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    protected $fillable = [
        'plate_number',
        'label',
        'capacity',
        'make_model',
        'status',
        'driver_staff_id',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => VehicleStatus::class,
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'driver_staff_id');
    }

    public function route(): HasOne
    {
        return $this->hasOne(TransportRoute::class);
    }

    public function activeRoute(): HasOne
    {
        return $this->hasOne(TransportRoute::class)
            ->where('status', TransportRouteStatus::Active);
    }

    public function displayName(): string
    {
        $label = trim((string) ($this->label ?? ''));

        return $label !== ''
            ? $label.' ('.$this->plate_number.')'
            : $this->plate_number;
    }
}
