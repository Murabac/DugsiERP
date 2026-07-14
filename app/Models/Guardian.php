<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guardian extends Model
{
    protected $fillable = [
        'student_id',
        'full_name',
        'phone',
        'relationship',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'relationship' => GuardianRelationship::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];
        $first = mb_substr($parts[0] ?? 'G', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

        return mb_strtoupper($first.($last !== $first ? $last : ''));
    }
}
