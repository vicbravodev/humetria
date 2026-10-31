<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The daily NOM-037 email digest a staff recipient got, so each gets at most one a day: the unique key is the lock, and the
 * delivery moment is where the next digest's «since the last one» starts. Holds no content, only that it went.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telework_digest_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('recipient', 40);
            $table->date('day');
            $table->timestampTz('delivered_at');

            $table->unique(['tenant_id', 'recipient', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telework_digest_deliveries');
    }
};
