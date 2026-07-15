<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 16); // present|absent|late|suspended
            $table->string('reason')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'class_id', 'date'], 'attendance_student_class_date_unique');
            $table->index(['class_id', 'date']);
        });

        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64); // absence_alert, fee_reminder, …
            $table->string('recipient_phone', 32)->nullable();
            $table->string('recipient_email')->nullable();
            $table->text('message_body');
            $table->string('status', 16); // stubbed|queued|sent|failed
            $table->foreignId('related_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('related_attendance_id')->nullable()->constrained('attendance_records')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
        Schema::dropIfExists('attendance_records');
    }
};
