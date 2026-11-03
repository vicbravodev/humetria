<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ley Silla: the positions where people work standing, with the answers of Figura 1 and the result that follows from them
 * (criterio C-06), and who holds each position. An assignment is ended, never deleted (criterio C-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standing_positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->string('name', 120);
            $table->text('tasks');
            $table->string('bipedestation_type', 10);
            $table->unsignedSmallInteger('continuous_minutes');
            $table->boolean('can_work_seated');
            $table->boolean('has_space_for_seat');
            $table->boolean('needs_to_be_nearby');
            $table->string('placement', 14)->nullable();
            $table->boolean('figure_one_applies');
            $table->unsignedSmallInteger('max_people_at_once');
            $table->jsonb('area_conditions');
            $table->text('tools')->nullable();
            $table->text('physical_load')->nullable();
            $table->text('attention_level')->nullable();
            $table->unsignedSmallInteger('default_standing_time')->nullable();
            $table->unsignedSmallInteger('default_posture_change')->nullable();
            $table->unsignedSmallInteger('default_surface')->nullable();
            $table->unsignedSmallInteger('default_movement_space')->nullable();
            $table->unsignedSmallInteger('default_pauses')->nullable();
            $table->boolean('nature_allows')->nullable();
            $table->text('nature_reason')->nullable();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->index(['tenant_id', 'work_center_id']);
            $table->unique(['id', 'tenant_id'], 'standing_positions_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE standing_positions
                ADD CONSTRAINT standing_positions_type_known CHECK (bipedestation_type IN ('static', 'dynamic', 'prolonged', 'combined')),
                ADD CONSTRAINT standing_positions_minutes_in_a_day CHECK (continuous_minutes <= 1440),
                ADD CONSTRAINT standing_positions_people_positive CHECK (max_people_at_once >= 1),
                ADD CONSTRAINT standing_positions_figure_one_follows_minutes CHECK (figure_one_applies = (continuous_minutes > 180)),
                ADD CONSTRAINT standing_positions_placement_follows_answers CHECK (placement IS NOT DISTINCT FROM (CASE
                    WHEN nature_allows IS FALSE THEN NULL
                    WHEN can_work_seated AND has_space_for_seat THEN 'at_workstation'
                    WHEN needs_to_be_nearby THEN 'nearby'
                    ELSE 'within_center'
                END)),
                ADD CONSTRAINT standing_positions_nature_needs_reason CHECK (nature_allows IS NOT FALSE OR length(btrim(coalesce(nature_reason, ''))) > 0),
                ADD CONSTRAINT standing_positions_default_standing_time_range CHECK (default_standing_time BETWEEN 1 AND 3),
                ADD CONSTRAINT standing_positions_default_posture_change_range CHECK (default_posture_change BETWEEN 1 AND 3),
                ADD CONSTRAINT standing_positions_default_surface_range CHECK (default_surface BETWEEN 1 AND 3),
                ADD CONSTRAINT standing_positions_default_movement_space_range CHECK (default_movement_space BETWEEN 1 AND 3),
                ADD CONSTRAINT standing_positions_default_pauses_range CHECK (default_pauses BETWEEN 1 AND 3)
        SQL);

        Schema::create('position_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('position_id');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['position_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('standing_positions')->restrictOnDelete();
            $table->index(['position_id', 'ends_on']);
            $table->unique(['id', 'tenant_id'], 'position_assignments_id_tenant_unique');
        });

        DB::statement('ALTER TABLE position_assignments ADD CONSTRAINT position_assignments_end_not_before_start CHECK (ends_on IS NULL OR ends_on >= starts_on)');
        // A person holds one position at a time; their history stays.
        DB::statement('CREATE UNIQUE INDEX position_assignments_one_current ON position_assignments (employee_id) WHERE ends_on IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('position_assignments');
        Schema::dropIfExists('standing_positions');
    }
};
