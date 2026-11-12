<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What was already told to each account, so a notice goes out once: the next amount and the loss of the discount by month
        // ("2026-12"), a balance past due by statement (its id).
        Schema::create('billing_notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_account_id')->constrained('billing_accounts')->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('reference', 60);
            $table->timestampTz('sent_at');
            $table->unique(['billing_account_id', 'kind', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_notices');
    }
};
