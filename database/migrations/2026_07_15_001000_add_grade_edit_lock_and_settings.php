<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('school_settings')->insert([
            'key' => 'grade_edit_window_days',
            'value' => '5',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('grades', function (Blueprint $table) {
            $table->timestamp('first_entered_at')->nullable()->after('entered_by');
        });

        // Backfill: treat existing rows as entered at created_at.
        DB::table('grades')->whereNull('first_entered_at')->update([
            'first_entered_at' => DB::raw('created_at'),
        ]);

        Schema::create('grade_edit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('old_score', 5, 2)->nullable();
            $table->decimal('new_score', 5, 2)->nullable();
            $table->string('old_letter', 1)->nullable();
            $table->string('new_letter', 1)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['grade_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_edit_logs');
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('first_entered_at');
        });
        Schema::dropIfExists('school_settings');
    }
};
