<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vacaciones: the annual statement of LFT art. 81 (criterio C-10), one per period granted on its anniversary. What it says
 * comes from the period, which never changes; here only when it was issued and when the person confirmed they received it.
 * No address nor device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('leave_entitlement_id')->unique();
            $table->timestampTz('issued_at');
            $table->timestampTz('acknowledged_at')->nullable();

            $table->foreign(['leave_entitlement_id', 'tenant_id', 'employee_id'], 'leave_statements_period_of_the_person')
                ->references(['id', 'tenant_id', 'employee_id'])->on('leave_entitlements')->restrictOnDelete();
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_statements');
    }
};
