<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\PortalVacationsPages;
use App\Modules\Leave\Support\PortalVisitor;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalVacationsPages::class, PortalVisitor::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']));
    // 18:00 UTC is noon in Mexico City.
    $this->travelTo('2026-11-02 18:00:00');
    Notification::fake();
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->boss = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz']);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Ana Solís']);
    $this->caro = Employee::factory()->reportingTo($this->boss)->create(['name' => 'Caro Luz']);
    // 3 days left of the older period, which can already prescribe, and 14 of the newer one.
    $this->older = portalPeriod($this->ana, 1, '2025-03-15', 12);
    LeaveEntry::factory()->of($this->older)->taken(9)->create();
    $this->newer = portalPeriod($this->ana, 2, '2026-03-15', 14);
    $this->token = resolve(IssuePortalLink::class)->handle($this->ana->id);
});

function portalPeriod(Employee $person, int $years, string $anniversary, int $statutory, int $extra = 0): LeaveEntitlement
{
    return LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'years_of_service' => $years, 'anniversary_on' => $anniversary, 'statutory_days' => $statutory, 'extra_days' => $extra]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function portalRequest(Employee $person, string $state = 'requested', array $attributes = []): LeaveRequest
{
    $factory = LeaveRequest::factory();
    $factory = match ($state) {
        'approved' => $factory->approved(),
        'taken' => $factory->taken(),
        'rejected' => $factory->rejected(),
        'cancelled' => $factory->cancelled(),
        default => $factory,
    };

    return $factory->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'approver_employee_id' => test()->boss->id, ...$attributes]);
}

it('lives under the personal link, outside the panel, throttled like the rest of the portal', function (): void {
    expect(route('portal.vacations.show', 'abc', absolute: false))->toBe('/portal/abc/vacations')
        ->and(Route::getRoutes()->getByName('portal.vacations.show')?->gatherMiddleware())->toContain('throttle:portal-views');
});

it('shows the person a row per period with days left, from the oldest', function (): void {
    $this->get(route('portal.vacations.show', $this->token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave/portal')
            ->where('token', $this->token)
            ->where('company', 'Acme')
            ->where('name', 'Ana Solís')
            ->where('today', '2026-11-02')
            ->where('advanced', 0)
            ->where('periods', [
                ['year' => 2025, 'granted' => 12, 'available' => 3, 'enjoyBy' => '2025-09-15', 'mayPrescribe' => true],
                ['year' => 2026, 'granted' => 14, 'available' => 14, 'enjoyBy' => '2026-09-15', 'mayPrescribe' => false],
            ]));
});

it('counts the extra days of a period and leaves out a period with nothing left', function (): void {
    LeaveEntry::factory()->of($this->older)->taken(3)->create();
    portalPeriod($this->ana, 3, '2026-10-15', 16, 2);

    $this->get(route('portal.vacations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->has('periods', 2)
            ->where('periods.0.year', 2026)
            ->where('periods.1', ['year' => 2026, 'granted' => 18, 'available' => 18, 'enjoyBy' => '2027-04-15', 'mayPrescribe' => false]));
});

it('marks a period as one that could prescribe only after the last day of its window', function (string $now, bool $marked): void {
    // The older period prescribes on 2026-09-15.
    $this->travelTo($now);

    $this->get(route('portal.vacations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('periods.0.mayPrescribe', $marked));
})->with([
    'the last day' => ['2026-09-15 18:00:00', false],
    'the day after' => ['2026-09-16 18:00:00', true],
]);

it('says how many days the person was given in advance', function (): void {
    LeaveEntry::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'leave_entitlement_id' => null, 'kind' => EntryKind::Taken, 'days' => -4]);

    $this->get(route('portal.vacations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('advanced', 4));
});

it('lists only their own requests, the newest first, a few at a time', function (): void {
    $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Paternidad']);
    $first = portalRequest($this->ana, 'rejected', ['starts_on' => '2026-06-01', 'ends_on' => '2026-06-05', 'working_days' => 5, 'had_balance' => false]);
    $second = portalRequest($this->ana, 'requested', ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-20', 'leave_type_id' => $type->id, 'had_balance' => null]);
    portalRequest($this->caro, 'requested', ['starts_on' => '2026-12-01', 'ends_on' => '2026-12-02']);

    $this->get(route('portal.vacations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requests.total', 2)
            ->where('requests.per_page', 10)
            ->where('requests.data', [
                ['id' => $second->id, 'type' => 'Paternidad', 'startsOn' => '2026-11-09', 'endsOn' => '2026-11-20', 'workingDays' => 9, 'status' => 'requested', 'statusLabel' => 'Solicitada', 'hadBalance' => null, 'rejectionReason' => null, 'canCancel' => true],
                ['id' => $first->id, 'type' => null, 'startsOn' => '2026-06-01', 'endsOn' => '2026-06-05', 'workingDays' => 5, 'status' => 'rejected', 'statusLabel' => 'Rechazada', 'hadBalance' => false, 'rejectionReason' => 'Cierre de mes', 'canCancel' => false],
            ]));
});

it('offers to cancel what waits and what was approved and has not started', function (string $state, string $startsOn, bool $offered): void {
    portalRequest($this->ana, $state, ['starts_on' => $startsOn, 'ends_on' => '2026-11-20']);

    $this->get(route('portal.vacations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('requests.data.0.canCancel', $offered));
})->with([
    'waiting' => ['requested', '2026-11-09', true],
    'approved, starts tomorrow' => ['approved', '2026-11-03', true],
    'approved, starts today' => ['approved', '2026-11-02', false],
    'enjoyed' => ['taken', '2026-10-01', false],
    'cancelled' => ['cancelled', '2026-11-09', false],
]);

it('turns the pages of the requests', function (): void {
    foreach (range(1, 12) as $month) {
        portalRequest($this->ana, 'taken', ['starts_on' => sprintf('2025-%02d-01', $month), 'ends_on' => sprintf('2025-%02d-02', $month)]);
    }

    $this->get(route('portal.vacations.show', [$this->token, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requests.total', 12)
            ->where('requests.current_page', 2)
            ->has('requests.data', 2)
            ->where('requests.data.1.startsOn', '2025-01-01'));
});

it('offers the leave types in use, by name', function (): void {
    $care = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cuidados']);
    $adoption = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Adopción']);
    LeaveType::factory()->archived()->create(['tenant_id' => $this->tenant->id, 'name' => 'Archivado']);
    LeaveType::factory()->create(['name' => 'De otra empresa']);

    $this->get(route('portal.vacations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('types', [
            ['id' => $adoption->id, 'name' => 'Adopción'],
            ['id' => $care->id, 'name' => 'Cuidados'],
        ]));
});

it("answers 'not found' to a link that is not valid, saying nothing else", function (): void {
    $this->get(route('portal.vacations.show', 'made-up-token'))->assertNotFound();

    $this->ana->update(['deactivated_at' => now()]);

    $this->get(route('portal.vacations.show', $this->token))->assertNotFound();
});

it("answers 'not found' in a company that does not use Vacaciones", function (): void {
    LeaveSetting::query()->delete();

    $this->get(route('portal.vacations.show', $this->token))->assertNotFound();
});

it('never shows a link of another company what is of this one', function (): void {
    portalRequest($this->ana);
    LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cuidados']);
    $other = Tenant::factory()->create(['trade_name' => 'Otra']);
    LeaveSetting::factory()->create(['tenant_id' => $other->id]);
    $stranger = Employee::factory()->create(['tenant_id' => $other->id, 'name' => 'Extraña']);
    workIn($other);
    $token = resolve(IssuePortalLink::class)->handle($stranger->id);

    $props = $this->get(route('portal.vacations.show', $token))->assertOk()->inertiaProps();

    expect($props['company'])->toBe('Otra')
        ->and($props['periods'])->toBe([])
        ->and($props['types'])->toBe([])
        ->and($props['requests']['data'])->toBe([]);
});

it('sends nothing of anybody else, no note and no amounts', function (): void {
    portalPeriod($this->caro, 1, '2026-01-10', 12);
    portalRequest($this->caro, 'requested', ['note' => 'Nota de Caro']);
    portalRequest($this->ana, 'requested', ['note' => 'Nota privada']);

    $props = $this->get(route('portal.vacations.show', $this->token))->assertOk()->inertiaProps();
    $sent = (string) json_encode($props, JSON_UNESCAPED_UNICODE);

    expect($props['periods'])->toHaveCount(2)
        ->and($sent)->not->toContain('Caro Luz', 'Nota de Caro', 'Nota privada', $this->caro->id, $this->boss->id, $this->ana->id, 'premium', 'amount', 'salary');
});

describe('asking for days', function (): void {
    it('previews, asks and cancels under the personal link, throttled as the actions of the portal', function (): void {
        expect(route('portal.vacations.preview', 'abc', absolute: false))->toBe('/portal/abc/vacations/preview')
            ->and(route('portal.vacations.requests.store', 'abc', absolute: false))->toBe('/portal/abc/vacations/requests')
            ->and(route('portal.vacations.requests.cancel', ['abc', 'r1'], absolute: false))->toBe('/portal/abc/vacations/requests/r1/cancel')
            ->and(Route::getRoutes()->getByName('portal.vacations.preview')?->gatherMiddleware())->toContain('throttle:portal-views')
            ->and(Route::getRoutes()->getByName('portal.vacations.requests.store')?->gatherMiddleware())->toContain('throttle:portal-actions')
            ->and(Route::getRoutes()->getByName('portal.vacations.requests.cancel')?->gatherMiddleware())->toContain('throttle:portal-actions');
    });

    it('says, before sending, how many working days the dates are and whether the person has them', function (string $from, string $to, array $expected): void {
        $this->getJson(route('portal.vacations.preview', [$this->token, 'starts_on' => $from, 'ends_on' => $to]))
            ->assertOk()
            ->assertExactJson($expected);
    })->with([
        // Two weeks from Monday to Friday, less the third Monday of November.
        'nine days they have' => ['2026-11-09', '2026-11-20', ['workingDays' => 9, 'hadBalance' => true, 'shortBy' => 0, 'continuousNotice' => true]],
        'twelve days in a row' => ['2026-11-09', '2026-11-25', ['workingDays' => 12, 'hadBalance' => true, 'shortBy' => 0, 'continuousNotice' => false]],
        'more than they have' => ['2026-11-09', '2026-12-11', ['workingDays' => 24, 'hadBalance' => false, 'shortBy' => 7, 'continuousNotice' => false]],
    ]);

    it('previews a leave, which uses no balance', function (): void {
        $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->getJson(route('portal.vacations.preview', [$this->token, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-20', 'leave_type_id' => $type->id]))
            ->assertOk()
            ->assertExactJson(['workingDays' => 9, 'hadBalance' => null, 'shortBy' => 0, 'continuousNotice' => false]);
    });

    it('says in Spanish why dates cannot be asked, before sending', function (): void {
        $this->getJson(route('portal.vacations.preview', [$this->token, 'starts_on' => '2026-11-07', 'ends_on' => '2026-11-08']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.ends_on.0', 'Esas fechas no incluyen ningún día laborable.');

        $this->getJson(route('portal.vacations.preview', [$this->token, 'starts_on' => '2026-11-09']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.ends_on.0', 'El campo fecha de fin es obligatorio.');
    });

    it('sends the request to whoever answers it and tells the person', function (): void {
        $this->post(route('portal.vacations.requests.store', $this->token), ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-20', 'note' => ' Viaje familiar ', 'employee_id' => $this->caro->id])
            ->assertRedirect(route('portal.vacations.show', $this->token))
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Enviamos su solicitud. Le avisaremos cuando la respondan.']);

        $request = LeaveRequest::query()->sole();
        expect($request->only(['employee_id', 'leave_type_id', 'working_days', 'note', 'approver_employee_id', 'recorded_by', 'had_balance']))->toBe([
            'employee_id' => $this->ana->id, 'leave_type_id' => null, 'working_days' => 9, 'note' => 'Viaje familiar', 'approver_employee_id' => $this->boss->id, 'recorded_by' => null, 'had_balance' => true,
        ])->and($request->status->value)->toBe('requested')
            ->and($request->starts_on->toDateString())->toBe('2026-11-09')
            ->and($request->ends_on->toDateString())->toBe('2026-11-20');
    });

    it('sends a leave of a type of the company, and a request without the days', function (): void {
        $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('portal.vacations.requests.store', $this->token), ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-10', 'leave_type_id' => $type->id])->assertRedirect();
        $this->post(route('portal.vacations.requests.store', $this->token), ['starts_on' => '2026-11-23', 'ends_on' => '2026-12-31'])->assertRedirect()->assertSessionHasNoErrors();

        expect(LeaveRequest::query()->orderBy('starts_on')->get()->map(fn (LeaveRequest $request): array => [$request->leave_type_id, $request->had_balance])->all())
            ->toBe([[$type->id, null], [null, false]]);
    });

    it('says in Spanish, on its field, why a request cannot be made', function (array $data, string $field, string $message): void {
        portalRequest($this->ana, 'approved', ['starts_on' => '2026-12-01', 'ends_on' => '2026-12-04']);
        $archived = LeaveType::factory()->archived()->create(['tenant_id' => $this->tenant->id]);
        $foreign = LeaveType::factory()->create();
        $data = array_map(fn (string $value): string => str_replace(['{archived}', '{foreign}'], [$archived->id, $foreign->id], $value), $data);

        $this->from(route('portal.vacations.show', $this->token))
            ->post(route('portal.vacations.requests.store', $this->token), $data)
            ->assertRedirect(route('portal.vacations.show', $this->token))
            ->assertSessionHasErrors([$field => $message]);

        expect(LeaveRequest::query()->count())->toBe(1);
    })->with([
        'already gone' => [['starts_on' => '2026-11-01', 'ends_on' => '2026-11-06'], 'starts_on', 'La fecha de inicio ya pasó. Elija una fecha de hoy en adelante.'],
        'a weekend' => [['starts_on' => '2026-11-07', 'ends_on' => '2026-11-08'], 'ends_on', 'Esas fechas no incluyen ningún día laborable.'],
        'over days already asked' => [['starts_on' => '2026-12-04', 'ends_on' => '2026-12-08'], 'starts_on', 'Ya tiene una solicitud en esas fechas. Cancélela o elija otras fechas.'],
        'longer than a year' => [['starts_on' => '2027-01-04', 'ends_on' => '2028-01-10'], 'ends_on', 'Una solicitud no puede durar más de un año. Divídala en varias.'],
        'an archived type' => [['starts_on' => '2026-11-09', 'ends_on' => '2026-11-10', 'leave_type_id' => '{archived}'], 'leave_type_id', 'Ese tipo de permiso ya no está disponible. Elija otro.'],
        'a type of another company' => [['starts_on' => '2026-11-09', 'ends_on' => '2026-11-10', 'leave_type_id' => '{foreign}'], 'leave_type_id', 'Ese tipo de permiso ya no está disponible. Elija otro.'],
        'the end before the start' => [['starts_on' => '2026-11-10', 'ends_on' => '2026-11-09'], 'ends_on', 'El campo fecha de fin debe ser una fecha posterior o igual a fecha de inicio.'],
        'no dates' => [[], 'starts_on', 'El campo fecha de inicio es obligatorio.'],
        'a note too long' => [['starts_on' => '2026-11-09', 'ends_on' => '2026-11-10', 'note' => str_repeat('a', 501)], 'note', 'El campo nota no debe tener más de 500 caracteres.'],
        'a type that is not an id' => [['starts_on' => '2026-11-09', 'ends_on' => '2026-11-10', 'leave_type_id' => 'x'], 'leave_type_id', 'El campo tipo de permiso debe ser un UUID válido.'],
    ]);

    it('lets the person cancel their own request', function (): void {
        $request = portalRequest($this->ana);

        $this->post(route('portal.vacations.requests.cancel', [$this->token, $request->id]))
            ->assertRedirect(route('portal.vacations.show', $this->token))
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Su solicitud quedó cancelada.']);

        expect($request->refresh()->status->value)->toBe('cancelled');
    });

    it('says a request that already passed can no longer be cancelled, and leaves it as it was', function (): void {
        $request = portalRequest($this->ana, 'taken', ['starts_on' => '2026-10-01', 'ends_on' => '2026-10-02']);

        $this->post(route('portal.vacations.requests.cancel', [$this->token, $request->id]))
            ->assertRedirect(route('portal.vacations.show', $this->token))
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Esta solicitud ya no se puede cancelar.']);

        expect($request->refresh()->status->value)->toBe('taken');
    });

    it("answers 'not found' when cancelling what is not theirs, and changes nothing", function (): void {
        $caros = portalRequest($this->caro);
        $other = Tenant::factory()->create();
        $foreign = LeaveRequest::factory()->create(['tenant_id' => $other->id]);

        $this->post(route('portal.vacations.requests.cancel', [$this->token, $caros->id]))->assertNotFound();
        $this->post(route('portal.vacations.requests.cancel', [$this->token, $foreign->id]))->assertNotFound();
        $this->post(route('portal.vacations.requests.cancel', [$this->token, 'not-an-id']))->assertNotFound();

        expect($caros->refresh()->status->value)->toBe('requested')
            ->and(LeaveRequest::query()->withoutGlobalScopes()->findOrFail($foreign->id)->status->value)->toBe('requested');
    });

    it("answers 'not found' to every action of a link that is not valid", function (): void {
        $request = portalRequest($this->ana);
        $dates = ['starts_on' => '2026-11-23', 'ends_on' => '2026-11-24'];

        $this->getJson(route('portal.vacations.preview', ['made-up', ...$dates]))->assertNotFound();
        $this->post(route('portal.vacations.requests.store', 'made-up'), $dates)->assertNotFound();
        $this->post(route('portal.vacations.requests.cancel', ['made-up', $request->id]))->assertNotFound();

        LeaveSetting::query()->delete();

        $this->post(route('portal.vacations.requests.store', $this->token), $dates)->assertNotFound();

        expect(LeaveRequest::query()->count())->toBe(1)
            ->and($request->refresh()->status->value)->toBe('requested');
    });
});
