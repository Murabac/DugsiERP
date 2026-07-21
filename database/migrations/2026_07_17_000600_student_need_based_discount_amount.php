<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'need_based_discount_amount')) {
            Schema::table('students', function (Blueprint $table) {
                $table->decimal('need_based_discount_amount', 10, 2)->default(0)->after('status');
            });
        }

        if (Schema::hasColumn('students', 'need_based_discount_percent')) {
            $monthly = (float) (DB::table('school_settings')
                ->where('key', 'monthly_fee_usd')
                ->value('value') ?? 45);

            foreach (DB::table('students')->where('need_based_discount_percent', '>', 0)->get() as $student) {
                $pct = (int) $student->need_based_discount_percent;
                $amount = round(min(100, max(0, $pct)) / 100 * $monthly, 2);
                DB::table('students')->where('id', $student->id)->update([
                    'need_based_discount_amount' => $amount,
                ]);
            }

            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('need_based_discount_percent');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('students', 'need_based_discount_percent')) {
            Schema::table('students', function (Blueprint $table) {
                $table->unsignedTinyInteger('need_based_discount_percent')->default(0)->after('status');
            });
        }

        if (Schema::hasColumn('students', 'need_based_discount_amount')) {
            $monthly = (float) (DB::table('school_settings')
                ->where('key', 'monthly_fee_usd')
                ->value('value') ?? 45);

            if ($monthly > 0) {
                foreach (DB::table('students')->where('need_based_discount_amount', '>', 0)->get() as $student) {
                    $pct = (int) round(((float) $student->need_based_discount_amount / $monthly) * 100);
                    DB::table('students')->where('id', $student->id)->update([
                        'need_based_discount_percent' => max(0, min(100, $pct)),
                    ]);
                }
            }

            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('need_based_discount_amount');
            });
        }
    }
};
