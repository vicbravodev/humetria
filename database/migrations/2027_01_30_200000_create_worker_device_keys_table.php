<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WorkerAccess: the device key of the app (plan B of the offline marks): an ECDSA P-256 key the phone makes and keeps, registered
 * online after a recent proof. Up to three active per person. Only its public key is kept; a revoked key keeps its row, with why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_device_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            // SPKI DER in base64url: 91 bytes make 122 characters.
            $table->text('public_key');
            $table->string('label', 60);
            $table->timestampTz('last_used_at', 6)->nullable();
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->string('revoked_reason', 20)->nullable();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE worker_device_keys
                ADD CONSTRAINT worker_device_keys_public_key_size CHECK (char_length(public_key) <= 200),
                ADD CONSTRAINT worker_device_keys_revoked_reason_valid CHECK (revoked_reason IN ('reissued', 'not_me', 'replaced', 'left_company')),
                ADD CONSTRAINT worker_device_keys_revoked_together CHECK ((revoked_at IS NULL) = (revoked_reason IS NULL));
            CREATE INDEX worker_device_keys_active_index ON worker_device_keys (tenant_id, employee_id) WHERE revoked_at IS NULL;
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_device_keys');
    }
};
