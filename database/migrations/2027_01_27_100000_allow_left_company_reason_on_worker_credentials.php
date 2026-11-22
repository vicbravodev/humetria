<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE worker_credentials
                DROP CONSTRAINT worker_credentials_revoked_reason_valid,
                ADD CONSTRAINT worker_credentials_revoked_reason_valid CHECK (
                    (revoked_at IS NULL AND revoked_reason IS NULL)
                    OR (revoked_at IS NOT NULL AND (revoked_reason IS NULL OR revoked_reason IN ('reissued', 'revoked_by_hr', 'not_me', 'left_company')))
                );
        SQL);
    }

    public function down(): void
    {
        DB::table('worker_credentials')->where('revoked_reason', 'left_company')->update(['revoked_reason' => 'revoked_by_hr']);
        DB::unprepared(<<<'SQL'
            ALTER TABLE worker_credentials
                DROP CONSTRAINT worker_credentials_revoked_reason_valid,
                ADD CONSTRAINT worker_credentials_revoked_reason_valid CHECK (
                    (revoked_at IS NULL AND revoked_reason IS NULL)
                    OR (revoked_at IS NOT NULL AND (revoked_reason IS NULL OR revoked_reason IN ('reissued', 'revoked_by_hr', 'not_me')))
                );
        SQL);
    }
};
