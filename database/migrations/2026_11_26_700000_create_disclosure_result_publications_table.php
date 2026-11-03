<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «This closed evaluation was published to the people» (num. 7.8; criterio C-54). It points at the evaluation and its center
 * and at who published and withdrew it, and at nothing else: no person, invitation or token, so it cannot tie anybody to an
 * answer. There is one active publication per evaluation; withdrawing and publishing again makes a new row and the earlier ones
 * stay (num. 10.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disclosure_result_publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('campaign_id');
            $table->uuid('work_center_id');
            $table->timestampTz('published_at');
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['campaign_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('campaigns')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->index(['tenant_id', 'work_center_id']);
        });

        DB::statement('ALTER TABLE disclosure_result_publications ADD CONSTRAINT disclosure_result_publications_withdrawal_complete CHECK ((withdrawn_at IS NULL) = (withdrawn_by IS NULL))');
        DB::statement('CREATE UNIQUE INDEX disclosure_result_publications_one_active ON disclosure_result_publications (campaign_id) WHERE withdrawn_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('disclosure_result_publications');
    }
};
