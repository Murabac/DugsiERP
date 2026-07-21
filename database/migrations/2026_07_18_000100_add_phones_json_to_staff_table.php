<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->json('phones')->nullable()->after('phone');
        });

        // Backfill from existing primary phone.
        $rows = DB::table('staff')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'phone']);

        foreach ($rows as $row) {
            DB::table('staff')->where('id', $row->id)->update([
                'phones' => json_encode([(string) $row->phone], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('phones');
        });
    }
};
