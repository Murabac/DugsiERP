<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('room', 32)->nullable()->after('capacity');
        });

        $classes = DB::table('classes')->select('id', 'form_level', 'section', 'room')->get();
        foreach ($classes as $class) {
            if ($class->room) {
                continue;
            }
            DB::table('classes')->where('id', $class->id)->update([
                'room' => 'R-'.$class->form_level.strtoupper((string) $class->section),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('room');
        });
    }
};
