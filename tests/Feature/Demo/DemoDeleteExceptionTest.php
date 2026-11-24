<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Survey;
use Database\Factories\Attendance\AttendanceMarkFactory;
use Database\Factories\Billing\UsageEventFactory;
use Database\Factories\Disclosure\AcknowledgmentFactory;
use Database\Factories\Disclosure\DisseminationRecordFactory;
use Database\Factories\Disclosure\DocumentCenterFactory;
use Database\Factories\Disclosure\DocumentFactory;
use Database\Factories\Engagement\CommentFactory;
use Database\Factories\Engagement\ResponseFactory;
use Database\Factories\Engagement\SurveyFactory;
use Database\Factories\Leave\LeaveEntryFactory;
use Database\Factories\Messaging\DeliveryEventFactory;
use Database\Factories\Responses\AnonymousResponseFactory;
use Database\Factories\Responses\TraumaScreeningFactory;
use Database\Factories\Seating\RiskAssessmentFactory;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\WorkerAccess\WorkerCredentialFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The insert-only tables let a row go only when its company is the demo, so resetting the demo can wipe it. A real
 * company's row is never deleted, and nobody's row is ever updated or truncated: the rule for evidence is unchanged.
 * Who can be the demo is covered in tests/Feature/Tenancy/DemoCompanyTest.php: one at most, never by mass assignment.
 */

/** An agreement of the attendance devices, written as the module does: there is no factory. */
function attendanceAgreement(string $tenantId): string
{
    $id = Str::uuid()->toString();
    DB::table('attendance_agreements')->insert(['id' => $id, 'tenant_id' => $tenantId, 'version' => 1, 'body' => 'Acepto marcar desde este teléfono.']);

    return $id;
}

/**
 * Runs a statement in its own savepoint, so the trigger refusing it leaves the test's transaction usable.
 *
 * @param  list<string>  $bindings
 */
function refusedByTrigger(string $sql, array $bindings = []): ?string
{
    try {
        DB::transaction(fn (): int => DB::affectingStatement($sql, $bindings));
    } catch (QueryException $refusal) {
        return $refusal->getMessage();
    }

    return null;
}

/** A survey of Clima y pulso to hang an answer or a comment on. */
function engagementSurvey(): Survey
{
    return SurveyFactory::new()->createOne();
}

/**
 * Each changed table: a row of a fresh company, the trigger's message, the SET an update would try (null where the table
 * lets an update through, as acknowledging does) and whether a truncate was refused before.
 *
 * @return array<string, array{string, Closure(): string, string, ?string, bool}>
 */
function insertOnlyTables(): array
{
    return [
        'anonymous_responses' => ['anonymous_responses', fn (): string => AnonymousResponseFactory::new()->createOne()->id, 'anonymous_responses is insert-only', 'id = id', true],
        'trauma_screenings' => ['trauma_screenings', fn (): string => TraumaScreeningFactory::new()->createOne()->id, 'trauma_screenings is insert-only', 'id = id', true],
        'engagement_responses' => ['engagement_responses', function (): string {
            $survey = engagementSurvey();

            return ResponseFactory::new()->createOne(['tenant_id' => $survey->tenant_id, 'survey_id' => $survey->id])->id;
        }, 'engagement_responses is insert-only', 'id = id', true],
        'engagement_comments' => ['engagement_comments', function (): string {
            $survey = engagementSurvey();

            return CommentFactory::new()->createOne(['tenant_id' => $survey->tenant_id, 'survey_id' => $survey->id])->id;
        }, 'engagement_comments is insert-only', 'id = id', true],
        'attendance_marks' => ['attendance_marks', fn (): string => AttendanceMarkFactory::new()->createOne()->id, 'attendance_marks is append-only', 'id = id', true],
        'attendance_agreements' => ['attendance_agreements', fn (): string => attendanceAgreement(TenantFactory::new()->createOne()->id), 'attendance_agreements is append-only', 'id = id', true],
        'attendance_agreement_acceptances' => ['attendance_agreement_acceptances', function (): string {
            $key = WorkerCredentialFactory::new()->createOne();
            $id = Str::uuid()->toString();
            DB::table('attendance_agreement_acceptances')->insert(['id' => $id, 'tenant_id' => $key->tenant_id, 'agreement_id' => attendanceAgreement($key->tenant_id), 'employee_id' => $key->employee_id, 'credential_id' => $key->id, 'accepted_at' => now()]);

            return $id;
        }, 'attendance_agreement_acceptances is append-only', 'id = id', true],
        'leave_entries' => ['leave_entries', fn (): string => LeaveEntryFactory::new()->createOne()->id, 'leave_entries is append-only', 'id = id', true],
        'messaging_delivery_events' => ['messaging_delivery_events', fn (): string => DeliveryEventFactory::new()->createOne()->id, 'messaging_delivery_events is append-only', 'status = status', true],
        'usage_events' => ['usage_events', fn (): string => UsageEventFactory::new()->createOne()->id, 'usage_events is append-only', 'id = id', true],
        'disclosure_documents' => ['disclosure_documents', fn (): string => DocumentFactory::new()->published()->createOne()->id, 'a published version is final', 'id = id', false],
        'disclosure_document_centers' => ['disclosure_document_centers', fn (): string => DocumentCenterFactory::new()->createOne()->id, 'a published version is final', 'id = id', false],
        'disclosure_acknowledgments' => ['disclosure_acknowledgments', fn (): string => AcknowledgmentFactory::new()->createOne()->id, 'an acknowledgment is evidence and is never deleted', null, false],
        'disclosure_dissemination_records' => ['disclosure_dissemination_records', fn (): string => DisseminationRecordFactory::new()->createOne()->id, 'a record of disclosure is evidence and never changes', 'id = id', false],
        'risk_assessments' => ['risk_assessments', fn (): string => RiskAssessmentFactory::new()->signed()->createOne()->id, 'a signed assessment is final', "other_aspects = 'otro'", false],
    ];
}

it('still refuses to delete a row of a real company while a demo company exists', function (string $table, string $id, string $message): void {
    // Another company is the demo: the exception must ask about the row's own company, not whether any demo exists.
    TenantFactory::new()->createOne(['is_demo' => true]);

    expect(refusedByTrigger("delete from {$table} where id = ?", [$id]))->toContain($message)
        ->and(DB::table($table)->where('id', $id)->exists())->toBeTrue();
})->with(insertOnlyTables());

it('deletes a row of the demo company, and still refuses to update it', function (string $table, string $id, string $message, ?string $set): void {
    becomeDemo(DB::table($table)->where('id', $id)->value('tenant_id'));

    if ($set !== null) {
        expect(refusedByTrigger("update {$table} set {$set} where id = ?", [$id]))->toContain($message);
    }

    expect(refusedByTrigger("delete from {$table} where id = ?", [$id]))->toBeNull()
        ->and(DB::table($table)->where('id', $id)->exists())->toBeFalse();
})->with(insertOnlyTables());

it('still refuses to empty the table, with only demo rows in it', function (string $table, string $id, string $message): void {
    becomeDemo(DB::table($table)->where('id', $id)->value('tenant_id'));

    expect(refusedByTrigger("truncate {$table} cascade"))->toContain($message)
        ->and(DB::table($table)->where('id', $id)->exists())->toBeTrue();
})->with(array_filter(insertOnlyTables(), fn (array $case): bool => $case[4]));

it('leaves the audit trail insert-only for the demo too', function (): void {
    $tenant = TenantFactory::new()->createOne();
    becomeDemo($tenant->id);
    $id = Str::uuid()->toString();
    DB::table('audit_entries')->insert(['id' => $id, 'tenant_id' => $tenant->id, 'event' => 'demo.test', 'created_at' => now()]);

    expect(refusedByTrigger('delete from audit_entries where id = ?', [$id]))->toContain('append-only');
});

it('puts every original rule back when rolled back', function (): void {
    $migration = require database_path('migrations/2027_01_19_100000_let_the_demo_company_be_wiped.php');
    $id = AttendanceMarkFactory::new()->createOne()->id;
    becomeDemo(DB::table('attendance_marks')->where('id', $id)->value('tenant_id'));

    $migration->down();

    expect(refusedByTrigger('delete from attendance_marks where id = ?', [$id]))->toContain('attendance_marks is append-only')
        ->and(DB::scalar("select prosrc from pg_proc where proname = 'disclosure_published_is_final'"))->toContain("TG_TABLE_NAME = 'disclosure_document_centers' OR OLD.status = 'published'");

    $migration->up();

    expect(refusedByTrigger('delete from attendance_marks where id = ?', [$id]))->toBeNull();
});
