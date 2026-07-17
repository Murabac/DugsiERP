<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('need_based_discount')->default(false)->after('status');
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('form_level'); // 1–4
            $table->string('academic_year', 16);
            $table->decimal('monthly_amount_usd', 10, 2);
            $table->unsignedTinyInteger('sibling_discount_percent')->default(0);
            $table->unsignedTinyInteger('need_based_discount_percent')->default(0);
            $table->date('effective_from')->nullable();
            $table->timestamps();

            $table->unique(['form_level', 'academic_year'], 'fee_structures_form_year_unique');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 32)->unique();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('academic_year', 16);
            $table->date('billing_month'); // first day of month
            $table->decimal('base_amount', 10, 2);
            $table->decimal('discount_applied', 10, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('amount_due', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('status', 16); // unpaid|partial|paid
            $table->timestamps();

            $table->unique(['student_id', 'billing_month'], 'invoices_student_billing_month_unique');
            $table->index(['academic_year', 'billing_month', 'status']);
            $table->index(['class_id', 'billing_month']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 32); // cash|mobile_money
            $table->string('receipt_number', 32)->unique();
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_structures');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('need_based_discount');
        });
    }
};
