<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The daily Ley Silla email a recipient got, so each gets at most one a day: the unique key is the lock. It holds no content,
 * only that it went.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_digest_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('recipient', 40);
            $table->date('day');
            $table->timestampTz('delivered_at');

            $table->unique(['tenant_id', 'recipient', 'day'], 'seating_digest_deliveries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seating_digest_deliveries');
    }
};
