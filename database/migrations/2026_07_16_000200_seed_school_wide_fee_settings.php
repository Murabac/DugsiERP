<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $defaults = [
            'monthly_fee_usd' => '45',
            'sibling_discount_percent' => '10',
            'need_based_discount_percent' => '20',
        ];

        foreach ($defaults as $key => $value) {
            $exists = DB::table('school_settings')->where('key', $key)->exists();
            if (! $exists) {
                DB::table('school_settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('school_settings')->whereIn('key', [
            'monthly_fee_usd',
            'sibling_discount_percent',
            'need_based_discount_percent',
        ])->delete();
    }
};
