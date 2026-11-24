<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\EvidenceLevel;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Models\AttendanceMark;
use Database\Factories\Attendance\AttendanceOfflineMarkFactory;
use Database\Factories\Attendance\MarkReviewFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * A mark is never edited nor deleted (criterio 5.4): not even a bug or a direct SQL statement can rewrite it.
 */
it('rejects an update of a mark made with SQL', function (): void {
    $mark = AttendanceMark::factory()->create();

    expect(fn () => DB::update("update attendance_marks set marked_at = now() - interval '1 hour' where id = ?", [$mark->id]))
        ->toThrow(QueryException::class, 'attendance_marks is append-only');
});

it('rejects a delete of a mark made with SQL', function (): void {
    $mark = AttendanceMark::factory()->create();

    expect(fn () => DB::delete('delete from attendance_marks where id = ?', [$mark->id]))
        ->toThrow(QueryException::class, 'attendance_marks is append-only');
});

it('rejects emptying the marks table', function (): void {
    AttendanceMark::factory()->create();

    expect(fn () => DB::statement('truncate attendance_marks cascade'))
        ->toThrow(QueryException::class, 'attendance_marks is append-only');
});

it('rejects a type the database does not know', function (): void {
    $mark = AttendanceMark::factory()->create();

    expect(fn () => DB::insert(
        "insert into attendance_marks (id, tenant_id, employee_id, work_center_id, type, marked_at, method, timezone)
            select gen_random_uuid(), tenant_id, employee_id, work_center_id, 'lunch', now(), method, timezone from attendance_marks where id = ?",
        [$mark->id],
    ))->toThrow(QueryException::class, 'attendance_marks_type_known');
});

it('rejects a correction without who made it and why', function (): void {
    $original = AttendanceMark::factory()->create();

    expect(fn () => AttendanceMark::factory()->create(['tenant_id' => $original->tenant_id, 'employee_id' => $original->employee_id, 'work_center_id' => $original->work_center_id, 'method' => MarkMethod::Correction, 'corrects_mark_id' => $original->id]))
        ->toThrow(QueryException::class, 'attendance_marks_correction_complete');
});

it('rejects a second correction of the same mark', function (): void {
    $original = AttendanceMark::factory()->create();
    $correction = AttendanceMark::factory()->correcting($original)->create();

    expect(fn () => AttendanceMark::factory()->correcting($original)->create(['corrected_by' => $correction->corrected_by]))->toThrow(QueryException::class, 'attendance_marks_corrects_mark_id_unique');
});

it('grades the evidence of each method (criterio 5.3)', function (MarkMethod $method, EvidenceLevel $level): void {
    expect($method->evidence())->toBe($level);
})->with([
    [MarkMethod::QrAndGeofence, EvidenceLevel::Strong],
    [MarkMethod::Geofence, EvidenceLevel::Medium],
    [MarkMethod::Qr, EvidenceLevel::Medium],
    [MarkMethod::Remote, EvidenceLevel::Basic],
    [MarkMethod::TrustedBrowser, EvidenceLevel::Basic],
    [MarkMethod::Correction, EvidenceLevel::Corrected],
    [MarkMethod::Field, EvidenceLevel::Strong],
    [MarkMethod::FieldUnlocated, EvidenceLevel::Medium],
    [MarkMethod::OfflineQr, EvidenceLevel::Medium],
    [MarkMethod::OfflineDeclared, EvidenceLevel::Basic],
]);

it('keeps the marks taken in a field center and the ones made without a connection', function (MarkMethod $method): void {
    expect(AttendanceMark::factory()->create(['method' => $method])->refresh()->method)->toBe($method);
})->with([MarkMethod::Field, MarkMethod::FieldUnlocated, MarkMethod::OfflineQr, MarkMethod::OfflineDeclared]);

it('makes the states of a mark made without a connection', function (): void {
    expect(AttendanceMark::factory()->offlineQr()->create()->refresh())->method->toBe(MarkMethod::OfflineQr)->credential_id->toBeNull()
        ->and(AttendanceMark::factory()->offlineDeclared()->create()->refresh())->method->toBe(MarkMethod::OfflineDeclared)->credential_id->toBeNull();
});

it('rejects a method the database does not know', function (): void {
    $mark = AttendanceMark::factory()->create();

    expect(fn () => DB::insert(
        "insert into attendance_marks (id, tenant_id, employee_id, work_center_id, type, marked_at, method, timezone)
            select gen_random_uuid(), tenant_id, employee_id, work_center_id, type, now(), 'offline_x', timezone from attendance_marks where id = ?",
        [$mark->id],
    ))->toThrow(QueryException::class, 'attendance_marks_method_known');
});

it('does not change a mark made without a connection when RH reviews it: the review is a row of its own', function (): void {
    $delivery = AttendanceOfflineMarkFactory::new()->accepted()->createOne();
    $before = DB::table('attendance_marks')->where('id', $delivery->mark_id)->first();

    MarkReviewFactory::new()->createOne(['tenant_id' => $delivery->tenant_id, 'mark_id' => $delivery->mark_id]);

    expect(DB::table('attendance_marks')->where('id', $delivery->mark_id)->first())->toEqual($before)
        ->and(DB::table('attendance_marks')->count())->toBe(1);
});
