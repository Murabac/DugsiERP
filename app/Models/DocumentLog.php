<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLog extends Model
{
    protected $table = 'documents_log';

    protected $fillable = [
        'document_number',
        'document_type',
        'student_id',
        'class_id',
        'payment_id',
        'term',
        'meta',
        'file_url',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'meta' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
