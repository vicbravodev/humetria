<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Comisión's review of a workplace (criterio C-12), the evidence it asks the person for, and the person's written
 * authorization for an in-person visit. Nothing here is deleted. A workplace's status and expiry are derived from the
 * reviews, never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The person authorizes the visit by confirming in their portal (confirmed_at + ip) or RH attaches the signed PDF.
        Schema::create('visit_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('workplace_id');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('requested_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->string('confirmed_ip', 45)->nullable();
            $table->string('document_path')->nullable();
            $table->timestampsTz();

            $table->foreign(['workplace_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('workplaces')->restrictOnDelete();
            $table->index('workplace_id');
            $table->unique(['id', 'tenant_id'], 'visit_authorizations_id_tenant_unique');
        });

        DB::statement('ALTER TABLE visit_authorizations ADD CONSTRAINT visit_authorizations_confirmation_pair CHECK ((confirmed_at IS NULL) = (confirmed_ip IS NULL))');

        Schema::create('workplace_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('workplace_id');
            $table->uuid('application_id')->nullable();
            $table->string('method', 12);
            $table->string('decision', 15);
            $table->text('notes');
            $table->text('action_plan')->nullable();
            $table->date('plan_due_on')->nullable();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('decided_at');
            $table->date('visited_on')->nullable();
            $table->uuid('visit_authorization_id')->nullable();
            $table->timestampsTz();

            $table->foreign(['workplace_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('workplaces')->restrictOnDelete();
            $table->foreign(['application_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('checklist_applications')->restrictOnDelete();
            $table->foreign(['visit_authorization_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('visit_authorizations')->restrictOnDelete();
            $table->index(['workplace_id', 'decided_at']);
            // An application is reviewed once; an authorization covers one visit.
            $table->unique('application_id');
            $table->unique('visit_authorization_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE workplace_reviews
                ADD CONSTRAINT workplace_reviews_method_known CHECK (method IN ('checklist', 'visit', 'remote_check')),
                ADD CONSTRAINT workplace_reviews_decision_known CHECK (decision IN ('valid', 'valid_with_plan', 'not_valid')),
                ADD CONSTRAINT workplace_reviews_checklist_has_application CHECK ((method = 'checklist') = (application_id IS NOT NULL)),
                ADD CONSTRAINT workplace_reviews_visit_has_authorization CHECK ((method = 'visit') = (visit_authorization_id IS NOT NULL AND visited_on IS NOT NULL)),
                ADD CONSTRAINT workplace_reviews_plan_complete CHECK ((decision = 'valid_with_plan') = (action_plan IS NOT NULL AND btrim(action_plan) <> '' AND plan_due_on IS NOT NULL)),
                ADD CONSTRAINT workplace_reviews_not_valid_has_notes CHECK (decision <> 'not_valid' OR btrim(notes) <> '')
        SQL);

        Schema::create('evidence_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('workplace_id');
            $table->string('kind', 12);
            $table->text('note');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('requested_at');
            $table->timestampTz('fulfilled_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['workplace_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('workplaces')->restrictOnDelete();
            $table->index('workplace_id');
            $table->unique(['id', 'tenant_id'], 'evidence_requests_id_tenant_unique');
        });

        DB::statement("ALTER TABLE evidence_requests ADD CONSTRAINT evidence_requests_kind_known CHECK (kind IN ('photos', 'remote_check'))");

        // The photos the person sent for a request, on the private disk.
        Schema::create('evidence_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('evidence_request_id');
            $table->string('path');
            $table->timestampTz('uploaded_at');
            $table->timestampsTz();

            $table->foreign(['evidence_request_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('evidence_requests')->restrictOnDelete();
            $table->index('evidence_request_id');
            $table->unique('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_photos');
        Schema::dropIfExists('evidence_requests');
        Schema::dropIfExists('workplace_reviews');
        Schema::dropIfExists('visit_authorizations');
    }
};
