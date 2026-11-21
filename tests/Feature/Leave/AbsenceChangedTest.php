<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Leave\Actions\CancelRequest;
use App\Modules\Leave\Actions\DecideRequest;
use App\Modules\Leave\Events\AbsenceChanged;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\Decider;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Event;

// The absences are part of what a person confirmed of their week (criterio 1.3 of jornada): approving or cancelling one
// tells the modules that read them, with ids and dates, never the type of the leave (criterio C-22).

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hr = User::factory()->create();
    $this->maternity = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maternidad']);
    Event::fake([AbsenceChanged::class]);
});

function leaveOfAna(object $test, string $state = 'requested'): LeaveRequest
{
    $factory = LeaveRequest::factory()->ofType($test->maternity);

    return ($state === 'requested' ? $factory : $factory->{$state}())->create(['employee_id' => $test->ana->id, 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-10', 'working_days' => 6]);
}

it('tells that an absence changed when a request is approved, with its ids and dates only', function (): void {
    $request = leaveOfAna($this);

    resolve(DecideRequest::class)->handle($request->id, true, Decider::hr($this->hr->id));

    Event::assertDispatched(AbsenceChanged::class, fn (AbsenceChanged $event): bool => $event->requestId === $request->id && $event->employeeId === $this->ana->id
        && $event->from === '2026-11-03' && $event->to === '2026-11-10');
    expect(array_keys(get_class_vars(AbsenceChanged::class)))->toBe(['requestId', 'employeeId', 'from', 'to']);
});

it('says nothing when a request is rejected: no absence changed', function (): void {
    resolve(DecideRequest::class)->handle(leaveOfAna($this)->id, false, Decider::hr($this->hr->id), 'No hay cobertura esa semana.');

    Event::assertNotDispatched(AbsenceChanged::class);
});

it('tells it when an approved or enjoyed request is cancelled, not one that was only waiting', function (string $state, bool $told): void {
    $request = leaveOfAna($this, $state);

    resolve(CancelRequest::class)->handle($request->id, Decider::hr($this->hr->id));

    $told ? Event::assertDispatched(AbsenceChanged::class, fn (AbsenceChanged $event): bool => $event->requestId === $request->id) : Event::assertNotDispatched(AbsenceChanged::class);
})->with(['approved' => ['approved', true], 'enjoyed' => ['taken', true], 'waiting' => ['requested', false]]);
