<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_waitlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('academic_year', 16);
            $table->unsignedSmallInteger('position');
            $table->string('status', 16)->default('waiting'); // waiting|enrolled|cancelled
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'class_id', 'academic_year']);
            $table->index(['class_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_waitlist');
    }
};
