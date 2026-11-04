<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The permanent link to the page of the workers (criterio C-55): one row per company. Its token is not stored: it is derived
 * from the company, `version` and the app key, so RH can see it again without changing it and the printed poster keeps
 * working; only its hash is kept, to find the row. Rotating moves the version and the old token stops working at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disclosure_page_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('enabled_at');
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('rotated_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique('tenant_id');
        });

        DB::statement('ALTER TABLE disclosure_page_links ADD CONSTRAINT disclosure_page_links_version_positive CHECK (version >= 1)');
        DB::statement('ALTER TABLE disclosure_page_links ADD CONSTRAINT disclosure_page_links_expiry_after_creation CHECK (expires_at IS NULL OR expires_at > created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('disclosure_page_links');
    }
};
