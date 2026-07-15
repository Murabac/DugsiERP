<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeEditLog extends Model
{
    protected $fillable = [
        'grade_id',
        'edited_by',
        'old_score',
        'new_score',
        'old_letter',
        'new_letter',
        'old_remarks',
        'new_remarks',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'old_score' => 'decimal:2',
            'new_score' => 'decimal:2',
        ];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class)->withTrashed();
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
