<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop any null-teacher slots left by older generators.
        DB::table('timetable_slots')->whereNull('teacher_id')->delete();

        // Keep one row per teacher/day/period if conflicts already exist.
        $duplicates = DB::table('timetable_slots')
            ->select('academic_year', 'day_of_week', 'period_number', 'teacher_id')
            ->whereNotNull('teacher_id')
            ->groupBy('academic_year', 'day_of_week', 'period_number', 'teacher_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $ids = DB::table('timetable_slots')
                ->where('academic_year', $dup->academic_year)
                ->where('day_of_week', $dup->day_of_week)
                ->where('period_number', $dup->period_number)
                ->where('teacher_id', $dup->teacher_id)
                ->orderBy('id')
                ->pluck('id');

            $keep = $ids->shift();
            if ($ids->isNotEmpty()) {
                DB::table('timetable_slots')->whereIn('id', $ids)->delete();
            }
            unset($keep);
        }

        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->unique(
                ['academic_year', 'day_of_week', 'period_number', 'teacher_id'],
                'timetable_teacher_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropUnique('timetable_teacher_period_unique');
        });
    }
};
