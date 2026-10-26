<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail: evidence of who did what, for the STPS and for
 * clients. A Postgres trigger rejects UPDATE, DELETE and TRUNCATE, so not
 * even a bug in the application can rewrite history. actor_id has no
 * foreign key on purpose: deleting a user must not touch audit rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('event')->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->string('request_id')->nullable()->index();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_entries_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_entries is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_entries_no_update_delete
                BEFORE UPDATE OR DELETE ON audit_entries
                FOR EACH ROW EXECUTE FUNCTION audit_entries_append_only();

            CREATE TRIGGER audit_entries_no_truncate
                BEFORE TRUNCATE ON audit_entries
                FOR EACH STATEMENT EXECUTE FUNCTION audit_entries_append_only();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
        DB::unprepared('DROP FUNCTION IF EXISTS audit_entries_append_only()');
    }
};
