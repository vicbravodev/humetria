<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de jornada: what Recursos Humanos decides about a mark made without a connection (criterio 5.4). Insert-only:
 * a decision is a new row and the one in force is the last by `seq` (a bigserial, so the order is deterministic even when
 * two rows share `created_at`). The demo company's rows may be deleted (2027_01_19_100000_let_the_demo_company_be_wiped).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_mark_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('mark_id');
            $table->string('decision', 9);
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['mark_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('attendance_marks')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE attendance_mark_reviews ADD COLUMN seq bigserial NOT NULL;

            CREATE INDEX attendance_mark_reviews_tenant_id_mark_id_seq_index ON attendance_mark_reviews (tenant_id, mark_id, seq);

            ALTER TABLE attendance_mark_reviews
                ADD CONSTRAINT attendance_mark_reviews_decision_known CHECK (decision IN ('verified', 'incorrect')),
                ADD CONSTRAINT attendance_mark_reviews_note_on_incorrect CHECK (decision = 'verified' OR length(btrim(coalesce(note, ''))) >= 5),
                ADD CONSTRAINT attendance_mark_reviews_note_length CHECK (char_length(note) <= 500);

            CREATE OR REPLACE FUNCTION attendance_mark_reviews_insert_only() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF EXISTS (SELECT 1 FROM tenants WHERE id = OLD.tenant_id AND is_demo) THEN
                        RETURN OLD;
                    END IF;
                END IF;

                RAISE EXCEPTION 'attendance_mark_reviews is insert-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER attendance_mark_reviews_no_update_delete
                BEFORE UPDATE OR DELETE ON attendance_mark_reviews
                FOR EACH ROW EXECUTE FUNCTION attendance_mark_reviews_insert_only();

            CREATE TRIGGER attendance_mark_reviews_no_truncate
                BEFORE TRUNCATE ON attendance_mark_reviews
                FOR EACH STATEMENT EXECUTE FUNCTION attendance_mark_reviews_insert_only();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_mark_reviews');
        DB::unprepared('DROP FUNCTION IF EXISTS attendance_mark_reviews_insert_only()');
    }
};
