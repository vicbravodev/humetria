<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Http\Controllers\CorrectionController;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Support\CorrectionPages;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia;

mutates(CorrectionController::class, CorrectionPages::class);

/** The queries of the queue, whatever its rows: the request's own, the counts, the page, its people and its marks. */
const QUEUE_QUERIES = 17;

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
