<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-037 settings, one row per company: how often Section II is applied (criterio C-14) and the consent text, which is
 * versioned because each acceptance keeps the text it accepted (C-25). Consent versions are never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telework_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->smallInteger('review_period_months')->default(12);
            $table->timestampsTz();

            $table->unique('tenant_id');
            $table->unique(['id', 'tenant_id'], 'telework_settings_id_tenant_unique');
        });

        DB::statement('ALTER TABLE telework_settings ADD CONSTRAINT telework_settings_period_range CHECK (review_period_months BETWEEN 1 AND 60)');

        Schema::create('telework_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->text('text');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['tenant_id', 'version'], 'telework_consents_version_unique');
            $table->unique(['id', 'tenant_id'], 'telework_consents_id_tenant_unique');
        });

        DB::statement('ALTER TABLE telework_consents ADD CONSTRAINT telework_consents_version_positive CHECK (version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('telework_consents');
        Schema::dropIfExists('telework_settings');
    }
};
