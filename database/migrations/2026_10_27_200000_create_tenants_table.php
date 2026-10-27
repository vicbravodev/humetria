<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Companies (tenants). The tenant itself carries no tenant_id. The checks repeat the console's
 * validation so no other path can store a value the form would reject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('legal_name', 200);
            $table->string('trade_name', 120);
            $table->string('rfc', 13)->unique('tenants_rfc_unique');
            $table->string('slug', 50)->unique('tenants_slug_unique');
            $table->timestampTz('trial_started_at')->nullable();
            $table->timestampTz('active_until')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tenants
                ADD CONSTRAINT tenants_legal_name_filled CHECK (btrim(legal_name) <> ''),
                ADD CONSTRAINT tenants_trade_name_filled CHECK (btrim(trade_name) <> ''),
                ADD CONSTRAINT tenants_rfc_format CHECK (rfc ~ '^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$'),
                ADD CONSTRAINT tenants_slug_format CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$' AND char_length(slug) BETWEEN 3 AND 50)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
