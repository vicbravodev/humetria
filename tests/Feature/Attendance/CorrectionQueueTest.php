<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Http\Controllers\CorrectionController;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\CorrectionPages;
use App\Modules\Attendance\Support\CorrectionPreview;
use App\Modules\Attendance\Support\TouchedWeeks;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia;

mutates(CorrectionController::class, CorrectionPages::class, CorrectionPreview::class, TouchedWeeks::class);

/** The queries of the queue, whatever its rows: the counts, the page, its people and marks, and the request in view with its day, weeks and schedule. */
const QUEUE_QUERIES = 25;

beforeEach(function (): void {
    // Wednesday 11 November 2026, 12:00 in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->start = AttendanceMark::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'type' => MarkType::Start,
        'marked_at' => CarbonImmutable::parse('2026-11-10 09:20', 'America/Mexico_City')->utc(),
        'latitude' => '19.432712', 'longitude' => '-99.133298', 'accuracy_m' => 12.5, 'distance_m' => 11.25,
    ]);
});

/** A request of `$person` asked `$daysAgo` days ago for a local time. */
function queued(Employee $person, ?AttendanceMark $mark, MarkType $type, string $local, int $daysAgo = 0, string $reason = 'Llegué a las 9, el QR no cargaba.'): CorrectionRequest
{
    return CorrectionRequest::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'mark_id' => $mark?->id, 'type' => $type, 'reason' => $reason,
        'proposed_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(), 'created_at' => now()->subDays($daysAgo),
    ]);
}

function queueUrl(array $query = []): string
{
    return route('tenant.attendance.corrections.index', ['acme', ...$query]);
}

describe('the queue', function (): void {
    it('shows RH the pending requests, oldest first, with the person, the times in local time, the reason and its age', function (): void {
        $request = queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00', 1);
        queued($this->ana, null, MarkType::End, '2026-11-10 23:50', 0, 'Olvidé marcar mi salida.');
        $this->actingAs($this->hr);

        $response = $this->get(queueUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/corrections')
            ->where('status', 'pending')
            ->where('filters', ['q' => ''])
            ->where('counts', ['pending' => 2, 'approved' => 0, 'rejected' => 0])
            ->where('canResolve', true)
            ->where('requests.total', 2)
            ->where('requests.per_page', 25)
            ->where('requests.data.0', [
                'id' => $request->id,
                'person' => ['name' => 'Ana López', 'jobTitle' => 'Operadora'],
                'type' => 'start',
                'requestedAt' => '2026-11-10 09:00',
                'previousAt' => '2026-11-10 09:20',
                'reason' => 'Llegué a las 9, el QR no cargaba.',
                'ageDays' => 1,
                'status' => 'pending',
                'note' => null,
            ])
            // 23:50 local is 05:50 of the next day in UTC: shown on its local day.
            ->where('requests.data.1.requestedAt', '2026-11-10 23:50')
            ->where('requests.data.1.previousAt', null));

        expect($response->getContent())->not->toContain('19.4327')->not->toContain('latitude')->not->toContain('accuracy')->not->toContain('distance')->not->toContain('device_id');
    });

    it('shows the requests of a state, its RH note with them', function (): void {
        $approved = queued($this->ana, null, MarkType::Start, '2026-11-09 09:00');
        $approved->update(['status' => CorrectionStatus::Rejected, 'resolved_by' => $this->hr->id, 'resolved_at' => now(), 'note' => 'Llegaste a las 9:20.']);
        queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        $this->actingAs($this->hr);

        $this->get(queueUrl(['status' => 'rejected']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('status', 'rejected')
            ->where('counts', ['pending' => 1, 'approved' => 0, 'rejected' => 1])
            ->where('requests.total', 1)
            ->where('requests.data.0.note', 'Llegaste a las 9:20.'));
        $this->get(queueUrl(['status' => 'everything']))->assertInertia(fn (AssertableInertia $page) => $page->where('status', 'pending')->where('requests.total', 1));
    });

    it('keeps the time the mark had before for an approved request, named as such, next to the time approved', function (): void {
        $request = queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        $this->actingAs($this->hr)->from(queueUrl())->post(route('tenant.attendance.corrections.approve', ['acme', $request->id]));

        $this->get(queueUrl(['status' => 'approved']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('requests.data.0.status', 'approved')
            ->where('requests.data.0.previousAt', '2026-11-10 09:20')
            ->where('requests.data.0.requestedAt', '2026-11-10 09:00')
            ->missing('requests.data.0.currentAt'));
    });

    it('pages the queue 25 at a time', function (): void {
        foreach (range(1, 26) as $day) {
            queued($this->ana, null, MarkType::Start, '2026-11-10 09:00', $day);
        }
        $this->actingAs($this->hr);

        $this->get(queueUrl(['page' => 2]))->assertInertia(fn (AssertableInertia $page) => $page->where('requests.total', 26)->has('requests.data', 1)->where('requests.current_page', 2));
    });

    it('searches by the person\'s name, without case or accents', function (): void {
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Roberto Díaz']);
        queued($this->ana, null, MarkType::Start, '2026-11-10 09:00');
        queued($bob, null, MarkType::Start, '2026-11-10 09:00');
        $this->actingAs($this->hr);

        $this->get(queueUrl(['q' => 'LOPEZ']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters', ['q' => 'LOPEZ'])
            ->where('requests.total', 1)
            ->where('requests.data.0.person.name', 'Ana López'));
    });

    it('reads the queue in a fixed number of queries, however many rows', function (): void {
        foreach (range(1, 5) as $day) {
            queued(EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]), null, MarkType::Start, '2026-11-10 09:00', $day);
        }
        queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        $this->actingAs($this->hr);

        $this->expectsDatabaseQueryCount(QUEUE_QUERIES);
        $this->get(queueUrl())->assertOk();
    });

    it('lets a viewer read the queue, without resolving', function (): void {
        queued($this->ana, null, MarkType::Start, '2026-11-10 09:00');
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->get(queueUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('canResolve', false)->where('requests.total', 1));
    });

    it('never lists the requests of another company', function (): void {
        $globex = Tenant::factory()->create();
        CorrectionRequest::factory()->create(['tenant_id' => $globex->id]);
        workIn($this->tenant);
        $this->actingAs($this->hr);

        $this->get(queueUrl())->assertInertia(fn (AssertableInertia $page) => $page->where('requests.total', 0)->where('counts.pending', 0));
    });

    it('answers 404 to a union account', function (): void {
        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));

        $this->get(route('tenant.attendance.corrections.index', $union->slug))->assertNotFound();
    });
});

describe('the request in view', function (): void {
    /** The request open in the page: `detail` of `selectedId`. */
    function openRequest(?string $id = null): Illuminate\Testing\TestResponse
    {
        return test()->get(queueUrl($id === null ? [] : ['request' => $id]))->assertOk();
    }

    it('opens the oldest request of the page, with its person, what they ask and why', function (): void {
        $first = queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00', 2);
        queued($this->ana, null, MarkType::End, '2026-11-10 18:00', 0, 'Olvidé mi salida.');
        $this->actingAs($this->hr);

        openRequest()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selectedId', $first->id)
            ->where('detail.id', $first->id)
            ->where('detail.person', ['name' => 'Ana López', 'jobTitle' => 'Operadora'])
            ->where('detail.type', 'start')
            ->where('detail.requestedAt', '2026-11-10 09:00')
            ->where('detail.previousAt', '2026-11-10 09:20')
            ->where('detail.reason', 'Llegué a las 9, el QR no cargaba.')
            ->where('detail.ageDays', 2)
            ->where('detail.status', 'pending')
            ->where('detail.note', null));
    });

    it('opens the one asked for, whatever its state, and the first one when it is unknown, of another company or not an id', function (): void {
        $first = queued($this->ana, null, MarkType::Start, '2026-11-09 09:00', 3);
        $second = queued($this->ana, null, MarkType::Start, '2026-11-10 09:00', 1);
        $globex = Tenant::factory()->create();
        $foreign = CorrectionRequest::factory()->create(['tenant_id' => $globex->id]);
        workIn($this->tenant);
        $this->actingAs($this->hr);

        openRequest($second->id)->assertInertia(fn (AssertableInertia $page) => $page->where('selectedId', $second->id)->where('detail.id', $second->id));
        foreach ([$foreign->id, '0b8a3a4e-0000-4000-8000-000000000000', 'ana'] as $other) {
            openRequest($other)->assertInertia(fn (AssertableInertia $page) => $page->where('selectedId', $first->id));
        }

        $second->update(['status' => CorrectionStatus::Rejected, 'resolved_by' => $this->hr->id, 'resolved_at' => now(), 'note' => 'Llegaste a las 9:20.']);
        // Resolved a moment ago, still in view: it is not on the tab of what is pending, yet it is the one shown.
        openRequest($second->id)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selectedId', $second->id)->where('detail.status', 'rejected')->where('detail.note', 'Llegaste a las 9:20.')->where('detail.effect', null));
    });

    it('has nothing in view when there is nothing to resolve', function (): void {
        $this->actingAs($this->hr);

        openRequest()->assertInertia(fn (AssertableInertia $page) => $page->where('selectedId', null)->where('detail', null));
    });

    it('shows that day in the record: its marks and the one asked, in local time', function (): void {
        $request = queued($this->ana, null, MarkType::End, '2026-11-10 18:00', 0, 'Olvidé mi salida.');
        $this->actingAs($this->hr);

        openRequest()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('detail.day', [
                'date' => '2026-11-10',
                'marks' => [['type' => 'start', 'time' => '09:20', 'nextDay' => false]],
                'asked' => ['type' => 'end', 'time' => '18:00', 'nextDay' => false],
            ])
            ->where('detail.id', $request->id));
    });

    it('puts a night that ends after midnight on the day it began', function (): void {
        AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'type' => MarkType::Start, 'marked_at' => CarbonImmutable::parse('2026-11-09 22:00', 'America/Mexico_City')->utc()]);
        queued($this->ana, null, MarkType::End, '2026-11-10 02:00', 0, 'Olvidé mi salida.');
        $this->actingAs($this->hr);

        openRequest()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('detail.day.date', '2026-11-09')
            ->where('detail.day.marks', [['type' => 'start', 'time' => '22:00', 'nextDay' => false]])
            ->where('detail.day.asked', ['type' => 'end', 'time' => '02:00', 'nextDay' => true])
            ->where('detail.effect.afterApproval', 'complete')
            ->where('detail.effect.sequenceOk', true));
    });

    it('does not pull an end to a start more than a day before it: it belongs to its own date', function (): void {
        // Ana\'s start is Tuesday 09:20; this end is Wednesday 15:00, almost 30 hours later.
        queued($this->ana, null, MarkType::End, '2026-11-11 15:00', 0, 'Olvidé mi salida.');
        $this->actingAs($this->hr);

        openRequest()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('detail.day.date', '2026-11-11')->where('detail.day.marks', [])->where('detail.day.asked', ['type' => 'end', 'time' => '15:00', 'nextDay' => false]));
    });

    it('tells whether the day ends complete once approved: with its end, or a start still open', function (string $type, string $local, ?bool $correcting, string $after): void {
        queued($this->ana, $correcting === true ? $this->start : null, MarkType::from($type), $local);
        $this->actingAs($this->hr);

        openRequest()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('detail.effect.sequenceOk', true)->where('detail.effect.afterApproval', $after));
    })->with([
        'the end that was forgotten' => ['end', '2026-11-10 18:00', false, 'complete'],
        'a start moved, still with no end' => ['start', '2026-11-10 09:00', true, 'missing_end'],
    ]);

    it('says the approval would be refused when it leaves the day out of order', function (): void {
        // An end before the start of the same day, and an end with no start at all.
        queued($this->ana, null, MarkType::End, '2026-11-10 08:00', 2);
        queued($this->ana, null, MarkType::End, '2026-11-08 18:30', 1);
        $this->actingAs($this->hr);

        $page = openRequest()->assertInertia(fn (AssertableInertia $page) => $page->where('detail.effect.sequenceOk', false));
        $second = $page->viewData('page')['props']['requests']['data'][1]['id'];
        openRequest($second)->assertInertia(fn (AssertableInertia $page) => $page->where('detail.effect.sequenceOk', false));
    });

    it('lists the confirmed weeks the approval would reopen, by their Monday', function (): void {
        queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-09']);
        WeeklyConfirmation::factory()->confirmed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-02']);
        $this->actingAs($this->hr);

        openRequest()->assertInertia(fn (AssertableInertia $page) => $page->where('detail.effect.reopensWeeks', ['2026-11-09']));
    });

    it('reopens nothing when the week was not confirmed', function (): void {
        queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        WeeklyConfirmation::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-09']);
        $this->actingAs($this->hr);

        openRequest()->assertInertia(fn (AssertableInertia $page) => $page->where('detail.effect.reopensWeeks', []));
    });

    it('says how the day is counted: with no schedule, or a rest day', function (): void {
        queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        $this->actingAs($this->hr);

        openRequest()->assertInertia(fn (AssertableInertia $page) => $page->where('detail.effect.noSchedule', true)->where('detail.effect.restDay', false));

        // Tuesday 10 November is the day of the request: a rest day of Ana's schedule.
        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'minutes_by_weekday' => [1 => 480, 2 => 0, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 0]]);
        openRequest()->assertInertia(fn (AssertableInertia $page) => $page->where('detail.effect.noSchedule', false)->where('detail.effect.restDay', true));

        AttendanceSchedule::query()->where('employee_id', $this->ana->id)->update(['minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 0]]);
        openRequest()->assertInertia(fn (AssertableInertia $page) => $page->where('detail.effect.restDay', false));
    });

    it('keeps the detail free of positions and devices', function (): void {
        queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        $this->actingAs($this->hr);

        $content = openRequest()->getContent();

        expect($content)->not->toContain('19.4327')->not->toContain('latitude')->not->toContain('accuracy')->not->toContain('distance')->not->toContain('device_id');
    });

    it('reads the request in view in the same queries whatever the people and requests of the page', function (): void {
        foreach (range(1, 5) as $day) {
            queued(EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]), null, MarkType::Start, '2026-11-10 09:00', $day);
        }
        queued($this->ana, $this->start, MarkType::End, '2026-11-10 18:00');
        $this->actingAs($this->hr);

        $this->expectsDatabaseQueryCount(QUEUE_QUERIES);
        openRequest();
    });
});

describe('resolving', function (): void {
    it('lets RH approve with an optional note, with a toast, back to the queue', function (): void {
        $request = queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        $this->actingAs($this->hr);

        $this->from(queueUrl())->post(route('tenant.attendance.corrections.approve', ['acme', $request->id]))
            ->assertRedirect(queueUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Solicitud aprobada. La marca quedó corregida.']);

        expect($request->fresh())->status->toBe(CorrectionStatus::Approved)->resolved_by->toBe($this->hr->id)->note->toBeNull();
    });

    it('lets RH reject with a note of 5 characters, in Spanish when it is missing', function (): void {
        $request = queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        $this->actingAs($this->hr);

        $this->from(queueUrl())->post(route('tenant.attendance.corrections.reject', ['acme', $request->id]), ['note' => 'No.'])->assertSessionHasErrors(['note' => 'El campo nota debe tener al menos 5 caracteres.']);
        expect($request->fresh()?->status)->toBe(CorrectionStatus::Pending);

        $this->from(queueUrl())->post(route('tenant.attendance.corrections.reject', ['acme', $request->id]), ['note' => 'Llegaste a las 9:20.'])
            ->assertRedirect(queueUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Solicitud rechazada. La persona verá tu nota.']);
        expect($request->fresh())->status->toBe(CorrectionStatus::Rejected)->note->toBe('Llegaste a las 9:20.');
    });

    it('tells RH what to do when the approval would leave the day out of order, keeping the request pending', function (): void {
        $request = queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'type' => MarkType::End, 'marked_at' => CarbonImmutable::parse('2026-11-10 08:00', 'America/Mexico_City')->utc()]);
        $this->actingAs($this->hr);

        $this->from(queueUrl())->post(route('tenant.attendance.corrections.approve', ['acme', $request->id]))
            ->assertRedirect(queueUrl())
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Con esa hora el día queda en desorden (una salida antes de su entrada o una entrada con otra abierta). Rechaza la solicitud o pide a la persona otra hora.']);

        expect($request->fresh()?->status)->toBe(CorrectionStatus::Pending);
    });

    it('tells RH a request was already resolved', function (string $action): void {
        $request = queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        $request->update(['status' => CorrectionStatus::Rejected, 'resolved_by' => $this->hr->id, 'resolved_at' => now(), 'note' => 'Llegaste a las 9:20.']);
        $this->actingAs($this->hr);

        $this->from(queueUrl())->post(route("tenant.attendance.corrections.{$action}", ['acme', $request->id]), ['note' => 'Revisado otra vez.'])
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Esta solicitud ya se resolvió.']);

        expect($request->fresh()?->status)->toBe(CorrectionStatus::Rejected);
    })->with(['approve', 'reject']);

    it('does not let a viewer resolve', function (string $action): void {
        $request = queued($this->ana, $this->start, MarkType::Start, '2026-11-10 09:00');
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->post(route("tenant.attendance.corrections.{$action}", ['acme', $request->id]), ['note' => 'Llegaste a las 9:20.'])->assertForbidden();

        expect($request->fresh()?->status)->toBe(CorrectionStatus::Pending);
    })->with(['approve', 'reject']);

    it('answers 404 to a request of another company, by its URL', function (string $action): void {
        $globex = Tenant::factory()->create();
        $foreign = CorrectionRequest::factory()->create(['tenant_id' => $globex->id]);
        workIn($this->tenant);
        $this->actingAs($this->hr);

        $this->post(route("tenant.attendance.corrections.{$action}", ['acme', $foreign->id]), ['note' => 'Llegaste a las 9:20.'])->assertNotFound();

        expect(CorrectionRequest::query()->withoutGlobalScopes()->find($foreign->id)?->status)->toBe(CorrectionStatus::Pending);
    })->with(['approve', 'reject']);
});
