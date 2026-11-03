<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The IMSS work risk premium of a company (LSS arts. 72-74): one rate per company and vigencia, set by the IMSS for each
 * employer and reviewed every year. rate_scaled is the rate times 10^7, so 7.58875 % (0.0758875) is 758875; the law keeps
 * it between 0.5 % and 15 % of the SBC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_risk_premiums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->bigInteger('rate_scaled');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'effective_from'], 'work_risk_premiums_tenant_date_unique');
        });

        DB::statement('ALTER TABLE work_risk_premiums ADD CONSTRAINT work_risk_premiums_rate_in_law CHECK (rate_scaled BETWEEN 50000 AND 1500000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('work_risk_premiums');
    }
};
