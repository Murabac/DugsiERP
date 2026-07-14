<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 32)->unique();
            $table->string('full_name');
            $table->date('dob')->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('qualification')->nullable();
            $table->string('subject_specialty')->nullable();
            $table->date('date_joined')->nullable();
            $table->decimal('fixed_salary_usd', 10, 2)->nullable();
            $table->string('role_label', 32); // teacher|admin|finance|librarian
            $table->string('status', 32)->default('active'); // active|on_leave|resigned
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('staff_id')
                ->references('id')
                ->on('staff')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
        });

        Schema::dropIfExists('staff');
    }
};
