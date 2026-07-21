<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'need_based_discount_percent')) {
            Schema::table('students', function (Blueprint $table) {
                $table->unsignedTinyInteger('need_based_discount_percent')->default(0)->after('status');
            });
        }

        if (Schema::hasColumn('students', 'need_based_discount')) {
            $defaultPct = (int) (DB::table('school_settings')
                ->where('key', 'need_based_discount_percent')
                ->value('value') ?? 20);
            $defaultPct = max(0, min(100, $defaultPct));

            DB::table('students')
                ->where(function ($q) {
                    $q->where('need_based_discount', true)
                        ->orWhere('need_based_discount', 1);
                })
                ->where('need_based_discount_percent', 0)
                ->update(['need_based_discount_percent' => $defaultPct]);

            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('need_based_discount');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('students', 'need_based_discount')) {
            Schema::table('students', function (Blueprint $table) {
                $table->boolean('need_based_discount')->default(false)->after('status');
            });
        }

        if (Schema::hasColumn('students', 'need_based_discount_percent')) {
            DB::table('students')
                ->where('need_based_discount_percent', '>', 0)
                ->update(['need_based_discount' => true]);

            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('need_based_discount_percent');
            });
        }
    }
};
