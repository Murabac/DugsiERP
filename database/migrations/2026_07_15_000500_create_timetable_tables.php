<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('teacher_subject_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->timestamps();

            $table->unique(['staff_id', 'subject_id']);
        });

        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('academic_year', 16);
            $table->string('day_of_week', 8); // sat|sun|mon|tue|wed
            $table->unsignedTinyInteger('period_number'); // 1–6
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('room', 32)->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'academic_year', 'day_of_week', 'period_number'], 'timetable_class_cell_unique');
            $table->index(['academic_year', 'day_of_week', 'period_number', 'teacher_id'], 'timetable_teacher_conflict_idx');
            $table->index(['academic_year', 'day_of_week', 'period_number', 'room'], 'timetable_room_conflict_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
        Schema::dropIfExists('teacher_subject_assignments');
        Schema::dropIfExists('subjects');
    }
};
