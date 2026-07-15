<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_boundaries', function (Blueprint $table) {
            $table->id();
            $table->string('letter', 1); // A–F (no E in school scale)
            $table->unsignedTinyInteger('min_percent');
            $table->unsignedTinyInteger('max_percent');
            $table->string('remark', 64)->nullable();
            $table->timestamps();

            $table->unique('letter');
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('term', 32); // Term 1|Term 2|Term 3|Final Exam
            $table->string('academic_year', 16);
            $table->decimal('score_percent', 5, 2);
            $table->string('letter_grade', 1);
            $table->string('remarks')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['student_id', 'class_id', 'subject_id', 'term', 'academic_year'],
                'grades_student_class_subject_term_year_unique'
            );
            $table->index(['class_id', 'term', 'academic_year']);
            $table->index(['student_id', 'term', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
        Schema::dropIfExists('grade_boundaries');
    }
};
