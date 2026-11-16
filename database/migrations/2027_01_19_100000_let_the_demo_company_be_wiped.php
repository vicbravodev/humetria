<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Resetting the demo company wipes everything it holds, its evidence included: the insert-only tables let a row be
 * DELETED only when its company is the demo (tenants.is_demo, one at most and never mass assignable). UPDATE and TRUNCATE
 * stay refused for every company, the demo too, and a real company's row is never deleted. The audit trail keeps its
 * trigger untouched (the demo's entries stay), as do the billing statements and payments (the demo has none).
 */
return new class extends Migration
{
    /** The demo's own rows go; every other row reaches the original body below. */
    private const string DEMO_MAY_DELETE = <<<'SQL'
        IF TG_OP = 'DELETE' THEN
            IF EXISTS (SELECT 1 FROM tenants WHERE id = OLD.tenant_id AND is_demo) THEN
                RETURN OLD;
            END IF;
        END IF;
    SQL;

    public function up(): void
    {
        foreach ($this->functions() as $name => [$original, $fixed]) {
            $this->replace($name, self::DEMO_MAY_DELETE."\n".($fixed ?? $original));
        }
    }

    public function down(): void
    {
        foreach ($this->functions() as $name => [$original]) {
            $this->replace($name, $original);
        }
    }

    /**
     * @param  literal-string  $name
     * @param  literal-string  $body
     */
    private function replace(string $name, string $body): void
    {
        DB::unprepared("CREATE OR REPLACE FUNCTION {$name}() RETURNS trigger LANGUAGE plpgsql AS \$\$\nBEGIN\n{$body}\nEND;\n\$\$");
    }

    /**
     * Each function's body as its migration wrote it, and the body up() runs after the demo's exception when it differs.
     *
     * @return array<literal-string, array{literal-string, literal-string|null}>
     */
    private function functions(): array
    {
        return [
            'anonymous_responses_insert_only' => ["RAISE EXCEPTION 'anonymous_responses is insert-only';", null],
            'trauma_screenings_insert_only' => ["RAISE EXCEPTION 'trauma_screenings is insert-only';", null],
            'engagement_responses_insert_only' => ["RAISE EXCEPTION 'engagement_responses is insert-only';", null],
            'engagement_comments_insert_only' => ["RAISE EXCEPTION 'engagement_comments is insert-only';", null],
            'attendance_marks_append_only' => ["RAISE EXCEPTION 'attendance_marks is append-only';", null],
            'leave_entries_append_only' => ["RAISE EXCEPTION 'leave_entries is append-only';", null],
            'usage_events_append_only' => ["RAISE EXCEPTION 'usage_events is append-only';", null],
            'attendance_agreements_append_only' => ["RAISE EXCEPTION '% is append-only', TG_TABLE_NAME;", null],
            'disclosure_acknowledgments_are_kept' => ["RAISE EXCEPTION 'an acknowledgment is evidence and is never deleted';", null],
            'disclosure_is_evidence' => ["RAISE EXCEPTION 'a record of disclosure is evidence and never changes';", null],
            'risk_assessments_signed_is_final' => [<<<'SQL'
                IF OLD.status = 'signed' AND (TG_OP = 'DELETE' OR
                    (to_jsonb(NEW) - 'reassessment_due' - 'recorded_in_document_id' - 'updated_at')
                    IS DISTINCT FROM (to_jsonb(OLD) - 'reassessment_due' - 'recorded_in_document_id' - 'updated_at')) THEN
                    RAISE EXCEPTION 'a signed assessment is final';
                END IF;

                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            SQL, null],
            'disclosure_published_is_final' => [
                <<<'SQL'
                    IF TG_TABLE_NAME = 'disclosure_document_centers' OR OLD.status = 'published' THEN
                        RAISE EXCEPTION 'a published version is final';
                    END IF;

                    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
                SQL,
                // PL/pgSQL reads OLD.status for a center too (it has no status), so a center failed with "no field status"
                // instead of this message: each table is asked apart. The rule is the same.
                <<<'SQL'
                    IF TG_TABLE_NAME = 'disclosure_document_centers' THEN
                        RAISE EXCEPTION 'a published version is final';
                    END IF;

                    IF OLD.status = 'published' THEN
                        RAISE EXCEPTION 'a published version is final';
                    END IF;

                    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
                SQL,
            ],
        ];
    }
};
