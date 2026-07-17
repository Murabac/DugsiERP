<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transport_assignments')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        Schema::table('transport_assignments', function (Blueprint $table) {
            $table->dropForeign(['stop_id']);
        });

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE transport_assignments MODIFY stop_id BIGINT UNSIGNED NULL');
        }

        Schema::table('transport_assignments', function (Blueprint $table) {
            $table->foreign('stop_id')->references('id')->on('transport_stops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Irreversible safely if nulls exist; no-op for rollback simplicity.
    }
};
