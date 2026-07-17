<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->date('billing_month'); // first day of month
            $table->string('status', 16); // draft|confirmed
            $table->unsignedInteger('staff_count')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique('billing_month');
            $table->index(['status', 'billing_month']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->string('employee_code', 32);
            $table->string('full_name');
            $table->string('role_label', 64)->nullable();
            $table->decimal('salary_usd', 10, 2);
            $table->string('payslip_number', 32)->unique();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'staff_id']);
            $table->index(['staff_id', 'payroll_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
    }
};
