<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('checkin_token', 64)->nullable()->unique()->after('status');
        });

        Schema::create('staff_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 16); // present|absent|late|on_leave
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('source', 16)->default('manual'); // manual|webauthn
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'date'], 'staff_attendance_staff_date_unique');
            $table->index(['date', 'status']);
        });

        Schema::create('staff_webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->json('transports')->nullable();
            $table->string('user_handle', 128)->nullable();
            $table->timestamps();

            $table->index('staff_id');
        });

        // Backfill personal check-in tokens for existing staff
        $ids = DB::table('staff')->whereNull('checkin_token')->pluck('id');
        foreach ($ids as $id) {
            DB::table('staff')->where('id', $id)->update([
                'checkin_token' => Str::random(48),
            ]);
        }

        DB::table('school_settings')->updateOrInsert(
            ['key' => 'staff_attendance_allowed_cidrs'],
            ['value' => '', 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('school_settings')->updateOrInsert(
            ['key' => 'staff_attendance_late_after'],
            ['value' => '08:00', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_webauthn_credentials');
        Schema::dropIfExists('staff_attendance_records');
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('checkin_token');
        });
    }
};
