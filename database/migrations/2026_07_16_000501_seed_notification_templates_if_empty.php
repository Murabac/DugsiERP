<?php

use App\Enums\NotificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('notification_templates')) {
            return;
        }

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

    public function down(): void
    {
        // Keep templates; they may have been edited.
    }
};
