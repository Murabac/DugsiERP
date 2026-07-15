<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            'school_name' => 'Qudus Secondary School',
            'school_location' => 'Somaliland',
            'school_tagline' => 'Secondary School',
        ] as $key => $value) {
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
            'school_name',
            'school_location',
            'school_tagline',
        ])->delete();
    }
};
