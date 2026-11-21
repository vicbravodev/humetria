<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RH resolves a disputed week (criterio 5.2) with a note: the week goes back to pending, keeping what the person disputed
 * and RH's answer, until the person confirms or disputes it again. A resolution is whole or absent, and only a pending week
 * that was disputed carries one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_weekly_confirmations', function (Blueprint $table) {
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('resolved_at')->nullable();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_weekly_confirmations
                DROP CONSTRAINT attendance_weekly_confirmations_status_consistent,
                ADD CONSTRAINT attendance_weekly_confirmations_status_consistent CHECK (
                    (status = 'pending' AND confirmed_at IS NULL AND credential_id IS NULL AND digest IS NULL AND (
                        (disputed_at IS NULL AND reason IS NULL AND resolution_note IS NULL AND resolved_by IS NULL AND resolved_at IS NULL)
                        OR (disputed_at IS NOT NULL AND btrim(reason) <> '' AND btrim(resolution_note) <> '' AND resolved_by IS NOT NULL AND resolved_at IS NOT NULL)
                    ))
                    OR (status = 'confirmed' AND confirmed_at IS NOT NULL AND disputed_at IS NULL AND reason IS NULL AND credential_id IS NOT NULL AND digest IS NOT NULL AND digest ~ '^[0-9a-f]{64}$'
                        AND resolution_note IS NULL AND resolved_by IS NULL AND resolved_at IS NULL)
                    OR (status = 'disputed' AND confirmed_at IS NULL AND disputed_at IS NOT NULL AND btrim(reason) <> '' AND credential_id IS NOT NULL AND digest IS NOT NULL AND digest ~ '^[0-9a-f]{64}$'
                        AND resolution_note IS NULL AND resolved_by IS NULL AND resolved_at IS NULL)
                );
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_weekly_confirmations
                DROP CONSTRAINT attendance_weekly_confirmations_status_consistent,
                ADD CONSTRAINT attendance_weekly_confirmations_status_consistent CHECK (
                    (status = 'pending' AND confirmed_at IS NULL AND disputed_at IS NULL AND reason IS NULL AND credential_id IS NULL AND digest IS NULL)
                    OR (status = 'confirmed' AND confirmed_at IS NOT NULL AND disputed_at IS NULL AND reason IS NULL AND credential_id IS NOT NULL AND digest IS NOT NULL AND digest ~ '^[0-9a-f]{64}$')
                    OR (status = 'disputed' AND confirmed_at IS NULL AND disputed_at IS NOT NULL AND btrim(reason) <> '' AND credential_id IS NOT NULL AND digest IS NOT NULL AND digest ~ '^[0-9a-f]{64}$')
                );
        SQL);

        Schema::table('attendance_weekly_confirmations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['resolution_note', 'resolved_at']);
        });
    }
};
