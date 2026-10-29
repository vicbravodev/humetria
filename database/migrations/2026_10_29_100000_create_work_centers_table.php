<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\MexicanState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The work centers of a company: NOM-035 applies per work center, and its report (7.7) names it.
 * They are archived, never deleted: their evaluations are evidence. A name is unique inside its company,
 * whatever the case, archived ones included, so restoring one never collides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_centers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('street', 150);
            $table->string('exterior_number', 20);
            $table->string('interior_number', 20)->nullable();
            $table->string('neighborhood', 120);
            $table->string('postal_code', 20);
            $table->string('municipality', 120);
            $table->char('state', 3);
            $table->string('main_activity', 200);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
        });

        $states = implode(', ', array_map(fn (MexicanState $state): string => "'{$state->value}'", MexicanState::cases()));

        DB::statement(<<<SQL
            ALTER TABLE work_centers
                ADD CONSTRAINT work_centers_name_filled CHECK (btrim(name) <> ''),
                ADD CONSTRAINT work_centers_street_filled CHECK (btrim(street) <> ''),
                ADD CONSTRAINT work_centers_exterior_number_filled CHECK (btrim(exterior_number) <> ''),
                ADD CONSTRAINT work_centers_interior_number_filled CHECK (interior_number IS NULL OR btrim(interior_number) <> ''),
                ADD CONSTRAINT work_centers_neighborhood_filled CHECK (btrim(neighborhood) <> ''),
                ADD CONSTRAINT work_centers_municipality_filled CHECK (btrim(municipality) <> ''),
                ADD CONSTRAINT work_centers_main_activity_filled CHECK (btrim(main_activity) <> ''),
                ADD CONSTRAINT work_centers_postal_code_format CHECK (postal_code ~ '^[0-9]{5}$'),
                ADD CONSTRAINT work_centers_state_known CHECK (state IN ({$states}))
        SQL);

        DB::statement('CREATE UNIQUE INDEX work_centers_tenant_name_unique ON work_centers (tenant_id, lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('work_centers');
    }
};
