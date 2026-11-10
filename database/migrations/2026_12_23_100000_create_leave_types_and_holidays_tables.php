<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacaciones: the kinds of leave that use no balance (criterio C-19) and the holidays a company adds to those of LFT
 * art. 74 (criterio C-04). A type with a key is one of the five every company starts with; the rest are the company's own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('key', 14)->nullable();
            $table->string('name', 80);
            $table->boolean('paid');
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'key']);
            $table->unique(['tenant_id', 'name']);
            $table->unique(['id', 'tenant_id'], 'leave_types_id_tenant_unique');
        });

        Schema::create('leave_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('name', 80);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'date']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE leave_types
                ADD CONSTRAINT leave_types_key_known CHECK (key IN ('paternity', 'maternity', 'adoption', 'special_paid', 'special_unpaid')),
                ADD CONSTRAINT leave_types_seeded_stay CHECK (key IS NULL OR archived_at IS NULL);
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_holidays');
        Schema::dropIfExists('leave_types');
    }
};
