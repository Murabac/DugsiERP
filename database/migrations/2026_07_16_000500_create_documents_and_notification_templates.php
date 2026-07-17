<?php

use App\Enums\NotificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_log', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 32)->unique();
            $table->string('document_type', 64);
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('term', 32)->nullable();
            $table->json('meta')->nullable();
            $table->string('file_url')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['document_type', 'generated_at']);
            $table->index(['student_id', 'document_type']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->unique();
            $table->string('name');
            $table->string('channel', 16)->default('sms'); // sms|email
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->seedDefaultTemplates();
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('documents_log');
    }

    private function seedDefaultTemplates(): void
    {
        $now = now();
        $rows = [
            [
                'type' => NotificationType::AbsenceAlert->value,
                'name' => 'Absence Alert',
                'channel' => 'sms',
                'body' => 'Dear parent, {student_name} ({class}) was absent on {date}. Please contact the school.',
                'variables' => json_encode(['student_name', 'class', 'date']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => NotificationType::FeeReminder->value,
                'name' => 'Fee Due Reminder',
                'channel' => 'sms',
                'body' => 'Dear parent, fee reminder for {student_name}: {amount} due by {due_date}.',
                'variables' => json_encode(['student_name', 'amount', 'due_date']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => NotificationType::FeeOverdue->value,
                'name' => 'Fee Overdue Notice',
                'channel' => 'sms',
                'body' => 'Dear parent, fee for {student_name} of {amount} is overdue by {days} days. Please pay soon.',
                'variables' => json_encode(['student_name', 'amount', 'days']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $row) {
            if (! DB::table('notification_templates')->where('type', $row['type'])->exists()) {
                DB::table('notification_templates')->insert($row);
            }
        }
    }
};
