<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: a person's linked device, a WebAuthn key (passkey) of the person, unlocked with their fingerprint,
 * face or PIN. Only its id, its public key, the last signature counter and its backup flags are stored. RH revokes a device and its row stays, with when. The acceptances and the marks
 * made with a device keep it alive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->text('credential_id')->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            // The key's backup flags (WebAuthn BE and BS): a synced passkey (iCloud Keychain, Google Password Manager) is
            // backup eligible and lives in the person's passkey account, not on one device. RH sees them.
            $table->boolean('backup_eligible')->default(false);
            $table->boolean('backup_state')->default(false);
            $table->string('label', 60);
            $table->timestampTz('enrolled_at');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->unique(['id', 'tenant_id'], 'attendance_devices_id_tenant_unique');
            $table->index(['tenant_id', 'employee_id']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_devices
                ADD CONSTRAINT attendance_devices_sign_count_in_range CHECK (sign_count BETWEEN 0 AND 4294967295),
                ADD CONSTRAINT attendance_devices_label_filled CHECK (btrim(label) <> '');
        SQL);

        Schema::table('attendance_agreement_acceptances', function (Blueprint $table) {
            $table->foreign(['device_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_devices')->restrictOnDelete();
        });

        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->foreign(['device_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_devices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->dropForeign(['device_id', 'tenant_id']);
        });
        Schema::table('attendance_agreement_acceptances', function (Blueprint $table) {
            $table->dropForeign(['device_id', 'tenant_id']);
        });
        Schema::dropIfExists('attendance_devices');
    }
};
