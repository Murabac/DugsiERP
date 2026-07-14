<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('form_level'); // 1–4
            $table->string('section', 8);
            $table->string('academic_year', 16);
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->unsignedBigInteger('homeroom_teacher_id')->nullable()->index(); // FK to staff in Week 3
            $table->string('status', 16)->default('active'); // active|archived
            $table->timestamps();

            $table->unique(['form_level', 'section', 'academic_year']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_code', 32)->unique();
            $table->string('full_name');
            $table->date('dob');
            $table->string('gender', 16); // male|female
            $table->string('photo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('previous_school')->nullable();
            $table->string('status', 32)->default('active'); // active|transferred|graduated|suspended
            $table->timestamps();
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone', 32);
            $table->string('relationship', 32); // father|mother|uncle|aunt|sibling|other
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->string('academic_year', 16);
            $table->unsignedSmallInteger('roll_number');
            $table->date('enrollment_date');
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->unique(['class_id', 'roll_number', 'academic_year']);
            $table->index(['student_id', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
        Schema::dropIfExists('classes');
    }
};
