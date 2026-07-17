<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the oldest log per attendance+type so the unique index can be added.
        $dupes = DB::table('notifications_log')
            ->select('related_attendance_id', 'type', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('related_attendance_id')
            ->groupBy('related_attendance_id', 'type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dupe) {
            DB::table('notifications_log')
                ->where('related_attendance_id', $dupe->related_attendance_id)
                ->where('type', $dupe->type)
                ->where('id', '!=', $dupe->keep_id)
                ->delete();
        }

        Schema::table('notifications_log', function (Blueprint $table) {
            $table->foreignId('related_invoice_id')
                ->nullable()
                ->after('related_attendance_id')
                ->constrained('invoices')
                ->nullOnDelete();

            $table->unique(
                ['related_attendance_id', 'type'],
                'notifications_log_attendance_type_unique'
            );

            $table->index(
                ['related_invoice_id', 'type', 'created_at'],
                'notifications_log_invoice_type_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notifications_log', function (Blueprint $table) {
            $table->dropUnique('notifications_log_attendance_type_unique');
            $table->dropIndex('notifications_log_invoice_type_created_index');
            $table->dropConstrainedForeignId('related_invoice_id');
        });
    }
};
