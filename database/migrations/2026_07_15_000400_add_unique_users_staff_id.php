<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the oldest user link when duplicates exist.
        $duplicates = DB::table('users')
            ->select('staff_id')
            ->whereNotNull('staff_id')
            ->groupBy('staff_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('staff_id');

        foreach ($duplicates as $staffId) {
            $keepId = DB::table('users')
                ->where('staff_id', $staffId)
                ->orderBy('id')
                ->value('id');

            DB::table('users')
                ->where('staff_id', $staffId)
                ->where('id', '!=', $keepId)
                ->update(['staff_id' => null]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['staff_id']);
        });
    }
};
