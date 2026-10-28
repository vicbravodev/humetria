<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations to join a company. Only the hash of the token is stored. A company has at most one
 * open (not accepted, not cancelled) invitation per role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('token_hash', 64)->unique('tenant_invitations_token_hash_unique');
            $table->timestampTz('expires_at', precision: 0);
            $table->timestampTz('accepted_at', precision: 0)->nullable();
            $table->timestampTz('cancelled_at', precision: 0)->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz(precision: 0);
        });

        DB::statement("ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_role_known CHECK (role IN ('owner'))");
        DB::statement('CREATE UNIQUE INDEX tenant_invitations_one_open ON tenant_invitations (tenant_id, role) WHERE accepted_at IS NULL AND cancelled_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
