<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('grades')->whereNull('first_entered_at')->update([
            'first_entered_at' => DB::raw('created_at'),
        ]);
        DB::table('grades')->whereNull('first_entered_at')->update([
            'first_entered_at' => now(),
        ]);

        if (! Schema::hasColumn('grades', 'deleted_at')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('grade_edit_logs', 'old_remarks')) {
            Schema::table('grade_edit_logs', function (Blueprint $table) {
                $table->string('old_remarks')->nullable();
                $table->string('new_remarks')->nullable();
            });
        }

        // Tighten first_entered_at + edited_by FK only on MySQL (already-migrated prod DBs).
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE grades MODIFY first_entered_at TIMESTAMP NOT NULL');

            $fk = collect(DB::select('
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = \'grade_edit_logs\'
                  AND COLUMN_NAME = \'edited_by\'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            '))->first();

            if ($fk && isset($fk->CONSTRAINT_NAME)) {
                DB::statement('ALTER TABLE grade_edit_logs DROP FOREIGN KEY `'.$fk->CONSTRAINT_NAME.'`');
            }

            DB::statement('ALTER TABLE grade_edit_logs MODIFY edited_by BIGINT UNSIGNED NULL');
            Schema::table('grade_edit_logs', function (Blueprint $table) {
                $table->foreign('edited_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('grade_edit_logs', 'old_remarks')) {
            Schema::table('grade_edit_logs', function (Blueprint $table) {
                $table->dropColumn(['old_remarks', 'new_remarks']);
            });
        }

        if (Schema::hasColumn('grades', 'deleted_at')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
