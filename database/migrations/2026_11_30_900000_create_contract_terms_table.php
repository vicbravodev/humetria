<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The terms in force of a benefit (the "base year" of a negotiation): from effective_from on, its value and, when it
 * grows with seniority, its table by years. The value is a whole number of hundredths (days, percent, cents or UMA: see
 * BenefitKind). A benefit has one term per date, so its history is kept. The composite key makes a term point to a
 * benefit of its own company only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('benefit_id');
            $table->date('effective_from');
            $table->bigInteger('value');
            $table->jsonb('tenure_table')->nullable();
            $table->timestampsTz();
            $table->foreign(['benefit_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('benefits')->restrictOnDelete();
            $table->unique(['benefit_id', 'effective_from'], 'contract_terms_benefit_date_unique');
            $table->unique(['id', 'tenant_id'], 'contract_terms_id_tenant_unique');
            $table->index(['tenant_id', 'benefit_id']);
        });

        DB::statement('ALTER TABLE contract_terms ADD CONSTRAINT contract_terms_value_not_negative CHECK (value >= 0)');
        DB::statement("ALTER TABLE contract_terms ADD CONSTRAINT contract_terms_table_list CHECK (tenure_table IS NULL OR jsonb_typeof(tenure_table) = 'array')");
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_terms');
    }
};
