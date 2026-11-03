<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-035, complaints channel (8.1 b): one channel per company. The access code is what the printed QR carries (the
 * credential of the company, not of a person) and can be rotated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('access_code', 16);
            $table->timestampTz('enabled_at')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->date('published_on')->nullable();
            $table->date('access_code_rotated_on')->nullable();
            $table->text('procedure');
            $table->smallInteger('acknowledge_within_days');
            $table->smallInteger('resolve_within_days');
            $table->smallInteger('retention_months');
            $table->timestampsTz();

            $table->unique('tenant_id');
            $table->unique('access_code');
            $table->unique(['id', 'tenant_id'], 'complaint_channels_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE complaint_channels
                ADD CONSTRAINT complaint_channels_acknowledge_within_days_range CHECK (acknowledge_within_days BETWEEN 1 AND 15),
                ADD CONSTRAINT complaint_channels_resolve_within_days_range CHECK (resolve_within_days BETWEEN 7 AND 180),
                ADD CONSTRAINT complaint_channels_retention_months_range CHECK (retention_months BETWEEN 12 AND 60),
                ADD CONSTRAINT complaint_channels_published_when_enabled CHECK ((enabled_at IS NULL) = (published_on IS NULL)),
                ADD CONSTRAINT complaint_channels_paused_only_when_enabled CHECK (paused_at IS NULL OR enabled_at IS NOT NULL),
                ADD CONSTRAINT complaint_channels_access_code_alphabet CHECK (access_code ~ '^[0-9A-HJKMNP-TV-Z]{16}$')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_channels');
    }
};
