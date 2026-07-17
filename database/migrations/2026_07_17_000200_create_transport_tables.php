<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number', 32)->unique();
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('capacity');
            $table->string('make_model')->nullable();
            $table->string('status', 16)->default('active'); // active|maintenance|retired
            $table->foreignId('driver_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->string('academic_year', 16);
            $table->string('status', 16)->default('active'); // active|inactive
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['academic_year', 'status']);
        });

        Schema::create('transport_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->time('approx_time')->nullable();
            $table->timestamps();

            $table->index(['route_id', 'sort_order']);
        });

        Schema::create('transport_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->foreignId('stop_id')->nullable()->constrained('transport_stops')->nullOnDelete();
            $table->string('academic_year', 16);
            $table->string('status', 16)->default('active'); // active|ended
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();

            $table->index(['route_id', 'status']);
            $table->index(['student_id', 'academic_year', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('transport_fee', 10, 2)->default(0)->after('discount_reason');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('transport_fee');
        });

        Schema::dropIfExists('transport_assignments');
        Schema::dropIfExists('transport_stops');
        Schema::dropIfExists('transport_routes');
        Schema::dropIfExists('vehicles');
    }
};
