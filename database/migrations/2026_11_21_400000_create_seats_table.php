<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ley Silla: the inventory of seats of a work center (criterios C-01, C-08 and C-11): where each is, which kind, the nine
 * characteristics of Quinta F, its condition, whether it is signposted and a photo. A seat is never deleted (C-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->uuid('position_id')->nullable();
            $table->text('location')->nullable();
            $table->string('kind', 16);
            $table->string('use', 11);
            $table->unsignedSmallInteger('quantity');
            foreach (['backrest', 'height', 'lumbar_support', 'adjustable', 'ergonomic', 'mobility', 'stability', 'armrests', 'footrest'] as $feature) {
                $table->boolean($feature);
            }
            $table->string('condition', 14);
            $table->boolean('signposted');
            $table->string('photo_path')->nullable();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->foreign(['position_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('standing_positions')->restrictOnDelete();
            $table->index(['tenant_id', 'work_center_id']);
            $table->index('position_id');
            $table->unique(['id', 'tenant_id'], 'seats_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE seats
                ADD CONSTRAINT seats_kind_known CHECK (kind IN ('high_stool', 'high_chair', 'ergonomic_chair', 'footrest')),
                ADD CONSTRAINT seats_use_known CHECK (use IN ('workstation', 'rest_area')),
                ADD CONSTRAINT seats_condition_known CHECK (condition IN ('good', 'needs_repair', 'out_of_service')),
                ADD CONSTRAINT seats_quantity_positive CHECK (quantity >= 1),
                ADD CONSTRAINT seats_rest_area_has_location CHECK (use <> 'rest_area' OR length(btrim(coalesce(location, ''))) > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
