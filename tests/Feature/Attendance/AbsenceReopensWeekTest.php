<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Attendance\Actions\ConfirmWeek;
use App\Modules\Attendance\Actions\DisputeWeek;
use App\Modules\Attendance\Actions\ReopenWeeks;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Exceptions\WeekRejected;
use App\Modules\Attendance\Listeners\ReopenOnAbsence;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Leave\Actions\CancelRequest;
use App\Modules\Leave\Actions\DecideRequest;
use App\Modules\Leave\Actions\TrimRequestsAfter;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\Decider;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;

mutates(ReopenWeeks::class, ReopenOnAbsence::class, WeekDigest::class);

// The absences are part of what the person confirmed (criterio 1.3, decision of the owner, H02): an absence approved or
// cancelled after the person answered reopens the week, which they answer again.

beforeEach(function (): void {
    // Wednesday 18 November 2026: the weeks of 2 and 9 November are over.
    $this->travelTo(CarbonImmutable::parse('2026-11-18 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->device = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->hr = User::factory()->create();
    $this->leave = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    foreach (['2026-11-03', '2026-11-10'] as $date) {
        foreach ([[MarkType::Start, "{$date} 09:00"], [MarkType::End, "{$date} 17:00"]] as [$type, $local]) {
            AttendanceMark::factory()->create([
                'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id,
                'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
            ]);
        }
    }
});

function anasDigest(string $monday): string
{
    return WeekDigest::of(test()->ana->id, WeekPeriod::ofDate(CarbonImmutable::parse($monday, 'America/Mexico_City'), 'America/Mexico_City'));
}

function anaConfirms(string $monday): WeeklyConfirmation
{
    return resolve(ConfirmWeek::class)->handle(test()->ana->id, CarbonImmutable::parse($monday, 'America/Mexico_City'), test()->device->id, anasDigest($monday));
}

function anasLeave(string $from, string $to, string $state = 'requested'): LeaveRequest
{
    $factory = LeaveRequest::factory()->ofType(test()->leave);

    return ($state === 'requested' ? $factory : $factory->{$state}())->create(['employee_id' => test()->ana->id, 'starts_on' => $from, 'ends_on' => $to, 'working_days' => 1]);
}

it('reopens the confirmed week a leave approved afterwards falls in, to the log and the audit trail without its type', function (): void {
    $week = anaConfirms('2026-11-02');
    $other = anaConfirms('2026-11-09');
    $request = anasLeave('2026-11-05', '2026-11-05');
    Log::spy();

    resolve(DecideRequest::class)->handle($request->id, true, Decider::hr($this->hr->id));

    expect($week->fresh())->status->toBe(WeekStatus::Pending)->confirmed_at->toBeNull()->digest->toBeNull()
        ->and($other->fresh()?->status)->toBe(WeekStatus::Confirmed);
    expect(AuditEntry::query()->where('event', 'attendance.week_reopened')->sole())
        ->subject_id->toBe($week->id)
        ->metadata->toEqual(['employee_id' => $this->ana->id, 'week_start' => '2026-11-02', 'leave_request_id' => $request->id]);
    Log::shouldHaveReceived('info')->with('attendance.week_reopened', ['confirmation_id' => $week->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-02']);
    expect(json_encode(AuditEntry::query()->get()->toArray(), JSON_THROW_ON_ERROR))->not->toContain('Maternidad');
});

it('reopens every answered week an absence spans, disputed ones too', function (): void {
    anaConfirms('2026-11-02');
    resolve(DisputeWeek::class)->handle($this->ana->id, CarbonImmutable::parse('2026-11-09', 'America/Mexico_City'), 'El martes salí a las 18:00.', $this->device->id, anasDigest('2026-11-09'));

    resolve(DecideRequest::class)->handle(anasLeave('2026-11-06', '2026-11-11')->id, true, Decider::hr($this->hr->id));

    expect(WeeklyConfirmation::query()->orderBy('week_start')->pluck('status')->all())->toBe([WeekStatus::Pending, WeekStatus::Pending]);
});

it('reopens the week when an approved absence in it is cancelled', function (): void {
    $request = anasLeave('2026-11-05', '2026-11-05', 'approved');
    $week = anaConfirms('2026-11-02');

    resolve(CancelRequest::class)->handle($request->id, Decider::hr($this->hr->id));

    expect($week->fresh()?->status)->toBe(WeekStatus::Pending);
});

it('reopens the week when a departure trims an absence that went past it', function (): void {
    $request = anasLeave('2026-11-10', '2026-11-13', 'approved');
    $week = anaConfirms('2026-11-09');
    $earlier = anaConfirms('2026-11-02');

    resolve(TrimRequestsAfter::class)->handle($this->ana->id, CarbonImmutable::parse('2026-11-11'));

    expect($request->fresh()?->ends_on->toDateString())->toBe('2026-11-11')
        ->and($week->fresh()?->status)->toBe(WeekStatus::Pending)
        ->and($earlier->fresh()?->status)->toBe(WeekStatus::Confirmed);
    expect(AuditEntry::query()->where('event', 'attendance.week_reopened')->sole()->metadata)
        ->toEqual(['employee_id' => $this->ana->id, 'week_start' => '2026-11-09', 'leave_request_id' => $request->id]);
});

it('gives the week another digest when an absence in it changes, so an answer about the old one is refused', function (): void {
    $seen = anasDigest('2026-11-02');
    anasLeave('2026-11-05', '2026-11-05', 'approved');

    expect(anasDigest('2026-11-02'))->not->toBe($seen)
        ->and(anasDigest('2026-11-09'))->toBe(anasDigest('2026-11-09'));
    expect(fn () => resolve(ConfirmWeek::class)->handle($this->ana->id, CarbonImmutable::parse('2026-11-02', 'America/Mexico_City'), $this->device->id, $seen))
        ->toThrow(fn (WeekRejected $e) => expect($e->reason)->toBe('week_changed'));
});

it('keeps the digest of a week with no absence as it always was: the ids of its marks', function (): void {
    $ids = AttendanceMark::query()->orderBy('id')->whereBetween('marked_at', ['2026-11-02', '2026-11-09'])->pluck('id')->all();

    expect(anasDigest('2026-11-02'))->toBe(hash('sha256', implode(',', $ids)));
});
