<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: how the people of a work center mark (criterio 4.5), as the company's policy says or in the field.
 * One row per center at most; a center without one goes by the policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_center_modes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id')->unique();
            $table->string('mode', 10);
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE attendance_center_modes ADD CONSTRAINT attendance_center_modes_mode_known CHECK (mode IN ('company', 'field'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_center_modes');
    }
};
