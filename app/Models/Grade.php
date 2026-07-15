<?php

namespace App\Models;

use App\Enums\AcademicTerm;
use App\Enums\LetterGrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'class_id',
        'subject_id',
        'term',
        'academic_year',
        'score_percent',
        'letter_grade',
        'remarks',
        'entered_by',
        'first_entered_at',
    ];

    protected function casts(): array
    {
        return [
            'term' => AcademicTerm::class,
            'score_percent' => 'decimal:2',
            'letter_grade' => LetterGrade::class,
            'first_entered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Grade $grade) {
            if ($grade->first_entered_at === null) {
                $grade->first_entered_at = now();
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function editLogs(): HasMany
    {
        return $this->hasMany(GradeEditLog::class)->latest();
    }
}
