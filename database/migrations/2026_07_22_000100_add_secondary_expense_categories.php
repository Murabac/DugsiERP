<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $names = [
            'Condolence / contingency',
            'Cleaning supplies',
            'Support staff wages',
            'Fee collection costs',
            'Tea / refreshments',
            'Exam marking',
            'Stationery',
        ];

        foreach ($names as $name) {
            $exists = DB::table('expense_categories')->where('name', $name)->exists();
            if ($exists) {
                continue;
            }
            DB::table('expense_categories')->insert([
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('expense_categories')->whereIn('name', [
            'Condolence / contingency',
            'Cleaning supplies',
            'Support staff wages',
            'Fee collection costs',
            'Tea / refreshments',
            'Exam marking',
            'Stationery',
        ])->delete();
    }
};
