<?php

use App\Models\SchoolSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! SchoolSetting::query()->where('key', 'staff_attendance_checkin_start')->exists()) {
            SchoolSetting::query()->create([
                'key' => 'staff_attendance_checkin_start',
                'value' => '07:00',
            ]);
        }

        if (! SchoolSetting::query()->where('key', 'staff_attendance_checkout_time')->exists()) {
            SchoolSetting::query()->create([
                'key' => 'staff_attendance_checkout_time',
                'value' => '16:00',
            ]);
        }
    }

    public function down(): void
    {
        SchoolSetting::query()->whereIn('key', [
            'staff_attendance_checkin_start',
            'staff_attendance_checkout_time',
        ])->delete();
    }
};
