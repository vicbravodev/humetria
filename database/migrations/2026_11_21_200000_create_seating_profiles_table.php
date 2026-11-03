<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ley Silla: what a work center declares (criterio C-03): its sector and whether it has people working standing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->string('sector', 12);
            $table->boolean('no_standing_positions')->default(false);
            $table->foreignId('declared_by')->constrained('users')->restrictOnDelete();
            $table->date('declared_on');
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique('work_center_id', 'seating_profiles_work_center_unique');
            $table->unique(['id', 'tenant_id'], 'seating_profiles_id_tenant_unique');
        });

        DB::statement("ALTER TABLE seating_profiles ADD CONSTRAINT seating_profiles_sector_known CHECK (sector IN ('services', 'commerce', 'analogous', 'industrial'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('seating_profiles');
    }
};
