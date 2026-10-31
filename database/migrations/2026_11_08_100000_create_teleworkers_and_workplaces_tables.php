<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The people who telework (one row per period), their workplaces, the checklist applications asked for each, and the
 * personal portal link of an employee (only its hash). Composite keys keep every row inside one company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teleworkers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('work_center_id');
            $table->string('status', 10);
            $table->smallInteger('telework_percentage');
            // The data of numeral 5.1: all optional while the person is an aspirant (joining the roster asks for them).
            $table->string('marital_status', 15)->nullable();
            $table->text('activities')->nullable();
            $table->string('job_profile', 200)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('exterior_number', 20)->nullable();
            $table->string('interior_number', 20)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('municipality', 120)->nullable();
            $table->char('state', 3)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->string('end_reason', 15)->nullable();
            $table->text('end_note')->nullable();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->index(['tenant_id', 'work_center_id']);
            $table->unique(['id', 'tenant_id'], 'teleworkers_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE teleworkers
                ADD CONSTRAINT teleworkers_status_known CHECK (status IN ('aspirant', 'active', 'ended')),
                ADD CONSTRAINT teleworkers_percentage_over_forty CHECK (telework_percentage > 40 AND telework_percentage <= 100),
                ADD CONSTRAINT teleworkers_marital_status_known CHECK (marital_status IS NULL OR marital_status IN ('single', 'married', 'free_union', 'separated', 'divorced', 'widowed')),
                ADD CONSTRAINT teleworkers_end_reason_known CHECK (end_reason IS NULL OR end_reason IN ('reversibility', 'left_company', 'transfer', 'other')),
                ADD CONSTRAINT teleworkers_ended_is_complete CHECK ((status = 'ended') = (ended_at IS NOT NULL AND end_reason IS NOT NULL)),
                ADD CONSTRAINT teleworkers_active_has_started CHECK (status = 'aspirant' OR started_at IS NOT NULL)
        SQL);
        // A person has one period that is not over; a new one opens only after the last ended.
        DB::statement("CREATE UNIQUE INDEX teleworkers_one_open_period ON teleworkers (employee_id) WHERE status <> 'ended'");

        Schema::create('workplaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('teleworker_id');
            $table->string('street', 150);
            $table->string('exterior_number', 20);
            $table->string('interior_number', 20)->nullable();
            $table->string('neighborhood', 120);
            $table->string('postal_code', 20);
            $table->string('municipality', 120);
            $table->char('state', 3);
            $table->timestampTz('retired_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['teleworker_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('teleworkers')->restrictOnDelete();
            $table->index('teleworker_id');
            $table->unique(['id', 'tenant_id'], 'workplaces_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE workplaces
                ADD CONSTRAINT workplaces_street_filled CHECK (btrim(street) <> ''),
                ADD CONSTRAINT workplaces_exterior_number_filled CHECK (btrim(exterior_number) <> ''),
                ADD CONSTRAINT workplaces_interior_number_filled CHECK (interior_number IS NULL OR btrim(interior_number) <> ''),
                ADD CONSTRAINT workplaces_neighborhood_filled CHECK (btrim(neighborhood) <> ''),
                ADD CONSTRAINT workplaces_municipality_filled CHECK (btrim(municipality) <> ''),
                ADD CONSTRAINT workplaces_postal_code_format CHECK (postal_code ~ '^[0-9]{5}$')
        SQL);

        Schema::create('portal_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->char('token_hash', 64)->unique();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            // One link per employee: issuing a new one replaces it.
            $table->unique('employee_id');
        });

        Schema::create('checklist_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('workplace_id');
            $table->uuid('checklist_id');
            $table->unsignedSmallInteger('section');
            $table->timestampTz('requested_at');
            $table->timestampTz('submitted_at')->nullable();
            $table->uuid('consent_id')->nullable();
            $table->timestampTz('consent_accepted_at')->nullable();
            $table->text('special_needs')->nullable();
            $table->timestampsTz();

            $table->foreign(['workplace_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('workplaces')->restrictOnDelete();
            $table->foreign(['checklist_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('checklists')->restrictOnDelete();
            $table->foreign(['consent_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('telework_consents')->restrictOnDelete();
            $table->index('workplace_id');
            $table->unique(['id', 'tenant_id'], 'checklist_applications_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE checklist_applications
                ADD CONSTRAINT checklist_applications_section_known CHECK (section IN (1, 2)),
                ADD CONSTRAINT checklist_applications_consent_pair CHECK ((consent_id IS NULL) = (consent_accepted_at IS NULL))
        SQL);
        // Asking again while one is still waiting returns that one.
        DB::statement('CREATE UNIQUE INDEX checklist_applications_one_pending ON checklist_applications (workplace_id, section) WHERE submitted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_applications');
        Schema::dropIfExists('portal_links');
        Schema::dropIfExists('workplaces');
        Schema::dropIfExists('teleworkers');
    }
};
