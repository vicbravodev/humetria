<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each move of a company between payers: who paid it, who pays it from the effective day, why, and who decided it.
        // The links (billing_account_tenants) say who paid when; this says why. Nothing here changes a company's access.
        Schema::create('company_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('from_account_id')->constrained('billing_accounts')->restrictOnDelete();
            // Null when the company is left without a payer.
            $table->foreignUuid('to_account_id')->nullable()->constrained('billing_accounts')->restrictOnDelete();
            $table->date('effective_on');
            $table->text('reason');
            $table->string('profile_before', 20)->nullable();
            $table->string('profile_after', 20)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['tenant_id', 'effective_on']);
            $table->index('from_account_id');
            $table->index('to_account_id');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE company_transfers
                ADD CONSTRAINT company_transfers_reason_filled CHECK (btrim(reason) <> ''),
                ADD CONSTRAINT company_transfers_moves CHECK (to_account_id IS NULL OR to_account_id <> from_account_id),
                ADD CONSTRAINT company_transfers_on_a_cut CHECK (EXTRACT(DAY FROM effective_on) = 1)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_transfers');
    }
};
