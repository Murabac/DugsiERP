<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'student_id',
        'class_id',
        'academic_year',
        'billing_month',
        'base_amount',
        'discount_applied',
        'discount_reason',
        'transport_fee',
        'amount_due',
        'amount_paid',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'base_amount' => 'decimal:2',
            'discount_applied' => 'decimal:2',
            'transport_fee' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => InvoiceStatus::class,
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function balance(): float
    {
        return max(0, round((float) $this->amount_due - (float) $this->amount_paid, 2));
    }

    public function refreshStatusFromPayments(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);
        $due = round((float) $this->amount_due, 2);

        $this->update([
            'amount_paid' => $paid,
            'status' => \App\Support\MonthlyInvoiceGenerator::statusForAmounts($due, $paid),
        ]);
    }
}
