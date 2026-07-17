<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('school_settings')->where('key', 'transport_fee_usd')->exists();
        if (! $exists) {
            DB::table('school_settings')->insert([
                'key' => 'transport_fee_usd',
                'value' => '15',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('school_settings')->where('key', 'transport_fee_usd')->delete();
    }
};
