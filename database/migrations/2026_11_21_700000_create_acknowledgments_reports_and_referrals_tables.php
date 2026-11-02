<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ley Silla: that the person was informed of the risks and measures of their assessment (Quinta H), the reports of a
 * damaged seat, and the referrals to medical attention (Quinta J). Nothing is deleted (criterio C-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_acknowledgments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('assessment_id');
            $table->timestampTz('acknowledged_at');
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['assessment_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('risk_assessments')->restrictOnDelete();
            // One confirmation per assessment: a new signed assessment asks again, a change of measures does not.
            $table->unique('assessment_id', 'risk_acknowledgments_assessment_unique');
        });

        Schema::create('seat_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->uuid('seat_id')->nullable();
            $table->uuid('position_id')->nullable();
            $table->text('description');
            $table->string('status', 8);
            $table->text('resolution')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->boolean('reported_via_portal');
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->foreign(['seat_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('seats')->restrictOnDelete();
            $table->foreign(['position_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('standing_positions')->restrictOnDelete();
            $table->index(['work_center_id', 'status', 'created_at']);
            $table->unique(['id', 'tenant_id'], 'seat_reports_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE seat_reports
                ADD CONSTRAINT seat_reports_status_known CHECK (status IN ('open', 'resolved')),
                ADD CONSTRAINT seat_reports_resolved_has_resolution CHECK ((status = 'resolved') = (resolved_at IS NOT NULL AND length(btrim(coalesce(resolution, ''))) > 0))
        SQL);

        Schema::create('medical_referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('work_center_id');
            $table->boolean('referred');
            $table->date('referred_on');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->index(['employee_id', 'referred_on']);
            $table->unique(['id', 'tenant_id'], 'medical_referrals_id_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_referrals');
        Schema::dropIfExists('seat_reports');
        Schema::dropIfExists('risk_acknowledgments');
    }
};
