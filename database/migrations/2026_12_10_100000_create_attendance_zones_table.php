<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: the geofenced zones of a work center (criterio 3.4). A mark that points to a zone keeps it alive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->string('name');
            $table->double('latitude');
            $table->double('longitude');
            $table->unsignedSmallInteger('radius_m');
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique(['id', 'tenant_id'], 'attendance_zones_id_tenant_unique');
            $table->index(['tenant_id', 'work_center_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_zones
                ADD CONSTRAINT attendance_zones_radius_in_range CHECK (radius_m BETWEEN 10 AND 5000),
                ADD CONSTRAINT attendance_zones_coordinates_in_range CHECK (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180);
        SQL);

        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->foreign(['zone_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_zones')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->dropForeign(['zone_id', 'tenant_id']);
        });
        Schema::dropIfExists('attendance_zones');
    }
};
