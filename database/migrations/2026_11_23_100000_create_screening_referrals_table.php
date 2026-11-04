<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The record of the channeling of a person whose Guía I requires a clinical assessment (num. 5.5, 5.8 c): referred or
 * attended, the day and the institution. One per person and campaign; no row means still pending. It points at the Guía I,
 * which is identified on purpose, and never at an anonymous response.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('campaign_id');
            $table->uuid('employee_id');
            $table->string('status', 20);
            $table->date('referred_on');
            $table->string('institution', 150);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('campaigns')->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['campaign_id', 'employee_id'])->references(['campaign_id', 'employee_id'])->on('trauma_screenings')->restrictOnDelete();
            $table->unique(['campaign_id', 'employee_id']);
        });

        DB::statement("ALTER TABLE screening_referrals ADD CONSTRAINT screening_referrals_status_known CHECK (status IN ('referred', 'attended'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_referrals');
    }
};
