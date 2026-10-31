<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\JoinRoster;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Actions\RegisterEquipment;
use App\Modules\Telework\Actions\RespondEquipment;
use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Data\RosterData;
use App\Modules\Telework\Enums\EquipmentStatus;
use App\Modules\Telework\Enums\MaritalStatus;
use App\Modules\Telework\Http\Controllers\EquipmentController;
use App\Modules\Telework\Http\Controllers\PortalEquipmentController;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\EquipmentItemFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RegisterEquipment::class, RespondEquipment::class, EquipmentController::class, PortalEquipmentController::class, JoinRoster::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    $this->teleworker = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    $this->actingAs($this->hr);
    workIn($this->tenant);
});

/** @return array<string, string> */
function equipmentForm(array $overrides = []): array
{
    return ['kind' => 'chair', 'description' => 'Silla ergonómica con soporte lumbar', 'delivered_on' => '2026-02-20', ...$overrides];
}

function equipmentUrl(?Teleworker $teleworker = null): string
{
    return route('tenant.telework.teleworkers.equipment.store', [test()->tenant, ($teleworker ?? test()->teleworker)->id]);
}

function equipmentPortal(): string
{
    return resolve(IssuePortalLink::class)->handle(test()->employee->id);
}

function pendingItem(array $state = []): EquipmentItem
{
    return EquipmentItemFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => test()->teleworker->id, ...$state]);
}

it('registers the equipment delivered to a person, pending their receipt', function (string $status): void {
    $teleworker = $status === 'aspirant' ? TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id]) : $this->teleworker;
    Log::spy();

    $this->post(equipmentUrl($teleworker), equipmentForm())->assertSessionHasNoErrors()->assertRedirect(route('tenant.telework.teleworkers.show', [$this->tenant, $teleworker->id]));

    $item = EquipmentItem::query()->sole();
    expect($item->status)->toBe(EquipmentStatus::Pending)
        ->and($item->teleworker_id)->toBe($teleworker->id)
        ->and($item->kind->value)->toBe('chair')
        ->and($item->description)->toBe('Silla ergonómica con soporte lumbar')
        ->and($item->delivered_on->toDateString())->toBe('2026-02-20')
        ->and($item->registered_by)->toBe($this->hr->id)
        ->and($item->responded_at)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'telework.equipment_registered')->sole()->subject_id)->toBe($item->id);
    Log::shouldHaveReceived('info')->with('telework.equipment_registered', ['equipment_id' => $item->id, 'teleworker_id' => $teleworker->id, 'kind' => 'chair'])->once();
})->with(['active' => 'active', 'aspirant' => 'aspirant']);

it('validates the equipment and refuses a future delivery date', function (array $overrides, string $field): void {
    $this->post(equipmentUrl(), equipmentForm($overrides))->assertSessionHasErrors($field);

    expect(EquipmentItem::query()->count())->toBe(0);
})->with([
    'unknown kind' => [['kind' => 'boat'], 'kind'],
    'no description' => [['description' => ' '], 'description'],
    'future date' => [['delivered_on' => '2026-03-02'], 'delivered_on'],
    'no date' => [['delivered_on' => ''], 'delivered_on'],
]);

it('accepts a delivery dated today', function (): void {
    $this->post(equipmentUrl(), equipmentForm(['delivered_on' => '2026-03-01']))->assertSessionHasNoErrors();

    expect(EquipmentItem::query()->count())->toBe(1);
});

it('does not register equipment for an ended period, another company or a role without permission', function (): void {
    $ended = TeleworkerFactory::new()->ended()->create(['tenant_id' => $this->tenant->id]);
    $this->post(equipmentUrl($ended), equipmentForm())->assertNotFound();

    $foreign = TeleworkerFactory::new()->active()->create();
    $this->post(equipmentUrl($foreign), equipmentForm())->assertNotFound();

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(equipmentUrl(), equipmentForm())->assertForbidden();
    expect(EquipmentItem::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('keeps one company\'s equipment out of another\'s queries', function (): void {
    pendingItem();
    $other = Tenant::factory()->create();
    workIn($other);

    expect(EquipmentItem::query()->count())->toBe(0);
    workIn($this->tenant);
    expect(EquipmentItem::query()->count())->toBe(1);
});

it('lists the equipment pending confirmation in the portal, oldest delivery first', function (): void {
    $later = pendingItem(['description' => 'Monitor', 'kind' => 'computer', 'delivered_on' => '2026-02-25']);
    $earlier = pendingItem(['description' => 'Silla', 'delivered_on' => '2026-02-10']);
    pendingItem(['status' => 'received', 'responded_at' => now()]);
    pendingItem(['status' => 'refused', 'refusal_reason' => 'No la necesito', 'responded_at' => now()]);

    $this->get(route('portal.show', equipmentPortal()))->assertInertia(fn (Assert $page) => $page
        ->has('pending.equipment', 2)
        ->where('pending.equipment.0', ['id' => $earlier->id, 'title' => 'Silla', 'kind' => 'Silla ergonómica', 'deliveredOn' => '2026-02-10'])
        ->where('pending.equipment.1.id', $later->id));
});

it('shows no equipment of an ended period or another person', function (): void {
    pendingItem();
    $other = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    $this->get(route('portal.show', resolve(IssuePortalLink::class)->handle($other->id)))->assertInertia(fn (Assert $page) => $page->has('pending.equipment', 0));
});

it('confirms receipt once, with the time and the address', function (): void {
    $item = pendingItem();
    $portal = resolve(OpenPortal::class)->handle(equipmentPortal());
    Log::spy();

    resolve(RespondEquipment::class)->handle($portal, $item->id, true, null, '203.0.113.9');

    $item->refresh();
    expect($item->status)->toBe(EquipmentStatus::Received)
        ->and($item->responded_at?->toIso8601String())->toBe('2026-03-01T12:00:00+00:00')
        ->and($item->responded_ip)->toBe('203.0.113.9')
        ->and($item->refusal_reason)->toBeNull();
    Log::shouldHaveReceived('info')->with('telework.equipment_responded', ['equipment_id' => $item->id, 'teleworker_id' => $this->teleworker->id, 'status' => 'received'])->once();
    expect(AuditEntry::query()->where('event', 'telework.equipment_responded')->sole()->subject_id)->toBe($item->id);

    $this->travelTo('2026-03-02 12:00:00');
    expect(fn () => resolve(RespondEquipment::class)->handle($portal, $item->id, false, 'Cambié de opinión', '198.51.100.1'))->toThrow(ValidationException::class);
    $item->refresh();
    expect($item->status)->toBe(EquipmentStatus::Received)->and($item->responded_ip)->toBe('203.0.113.9');
});

it('requires a reason to refuse, and keeps it without logging it', function (): void {
    $item = pendingItem();
    $portal = resolve(OpenPortal::class)->handle(equipmentPortal());

    foreach ([null, '', '   '] as $blank) {
        expect(fn () => resolve(RespondEquipment::class)->handle($portal, $item->id, false, $blank, null))->toThrow(ValidationException::class);
    }

    expect($item->refresh()->status)->toBe(EquipmentStatus::Pending);

    Log::spy();
    resolve(RespondEquipment::class)->handle($portal, $item->id, false, 'La silla no cabe en mi espacio', null);

    $item->refresh();
    expect($item->status)->toBe(EquipmentStatus::Refused)
        ->and($item->refusal_reason)->toBe('La silla no cabe en mi espacio')
        ->and($item->responded_ip)->toBeNull();
    Log::shouldHaveReceived('info')->with('telework.equipment_responded', ['equipment_id' => $item->id, 'teleworker_id' => $this->teleworker->id, 'status' => 'refused'])->once();
    expect(json_encode(AuditEntry::query()->where('event', 'telework.equipment_responded')->sole()->metadata))->not->toContain('no cabe');
});

it('ignores a reason when the person received the item', function (): void {
    $item = pendingItem();

    resolve(RespondEquipment::class)->handle(resolve(OpenPortal::class)->handle(equipmentPortal()), $item->id, true, 'texto suelto', null);

    expect($item->refresh()->refusal_reason)->toBeNull();
});

it('answers from the portal and sends the person back to it', function (): void {
    $token = equipmentPortal();
    $received = pendingItem();
    $refused = pendingItem();

    $this->post(route('portal.equipment.respond', [$token, $received->id]), ['received' => '1'])->assertRedirect(route('portal.show', $token));
    $this->post(route('portal.equipment.respond', [$token, $refused->id]), ['received' => '0'])->assertSessionHasErrors('reason');
    $this->post(route('portal.equipment.respond', [$token, $refused->id]), ['received' => '0', 'reason' => 'No la uso'])->assertRedirect(route('portal.show', $token));

    expect($received->refresh()->status)->toBe(EquipmentStatus::Received)
        ->and($refused->refresh()->status)->toBe(EquipmentStatus::Refused);
    $this->post(route('portal.equipment.respond', [$token, $received->id]), ['received' => '0', 'reason' => 'Otra vez'])->assertSessionHasErrors('equipment');
    $this->post(route('portal.equipment.respond', [$token, $received->id]), [])->assertSessionHasErrors('received');
});

it('gives a 404 for the equipment of another person, an unknown link or a malformed id', function (): void {
    $item = pendingItem();
    $outsider = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $outsider->id]);

    $this->post(route('portal.equipment.respond', [resolve(IssuePortalLink::class)->handle($outsider->id), $item->id]), ['received' => '1'])->assertNotFound();
    $this->post(route('portal.equipment.respond', ['dead', $item->id]), ['received' => '1'])->assertNotFound();
    $this->post(route('portal.equipment.respond', [equipmentPortal(), 'not-a-uuid']), ['received' => '1'])->assertNotFound();
    expect($item->refresh()->status)->toBe(EquipmentStatus::Pending);
});

it('refuses in the database a refusal without reason and a pending item that was answered', function (): void {
    // Each attempt in its own savepoint: a failed statement would otherwise poison the test's transaction.
    $blocked = fn (array $state) => expect(fn () => DB::transaction(fn () => pendingItem($state)))->toThrow(QueryException::class);

    $blocked(['status' => 'refused', 'responded_at' => now()]);
    $blocked(['status' => 'refused', 'responded_at' => now(), 'refusal_reason' => '  ']);
    $blocked(['status' => 'received', 'responded_at' => now(), 'refusal_reason' => 'x']);
    $blocked(['status' => 'pending', 'responded_at' => now()]);
    $blocked(['status' => 'received']);
    expect(EquipmentItem::query()->count())->toBe(0);
});

it('shows the equipment on the person\'s page', function (): void {
    $item = pendingItem(['description' => 'Silla']);

    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, $this->teleworker->id]))->assertInertia(fn (Assert $page) => $page
        ->where('teleworker.equipment.0.id', $item->id)
        ->where('teleworker.equipment.0.status', 'pending')
        ->where('teleworker.equipment.0.deliveredOn', '2026-02-20')
        ->where('teleworker.equipment.0.refusalReason', null));
});

describe('joining the roster (5.1 k)', function (): void {
    beforeEach(function (): void {
        $this->aspirant = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])->id]);
        $workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->aspirant->id]);
        WorkplaceReviewFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $workplace->id, 'decided_at' => '2026-02-01 12:00:00']);
    });

    function joinData(?string $reason): RosterData
    {
        return new RosterData(MaritalStatus::Single, 'Atención.', 'Analista', '4421234567', new AddressData('Calle Reforma', '12', null, 'Centro', '76000', 'Querétaro', 'QUE'), $reason);
    }

    it('joins with equipment registered and no reason', function (): void {
        EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->aspirant->id]);

        resolve(JoinRoster::class)->handle($this->aspirant, joinData(null), $this->hr->id);

        expect($this->aspirant->refresh()->status->value)->toBe('active')->and($this->aspirant->no_equipment_reason)->toBeNull();
    });

    it('joins with a reason for having none', function (): void {
        resolve(JoinRoster::class)->handle($this->aspirant, joinData('Usa su propio equipo.'), $this->hr->id);

        expect($this->aspirant->refresh()->no_equipment_reason)->toBe('Usa su propio equipo.');
    });

    it('does not join with neither equipment nor a reason', function (?string $reason): void {
        try {
            resolve(JoinRoster::class)->handle($this->aspirant, joinData($reason), $this->hr->id);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $failure) {
            expect($failure->errors())->toHaveKey('no_equipment_reason');
        }

        expect($this->aspirant->refresh()->status->value)->toBe('aspirant');
    })->with([null, '', '   ']);

    it('accepts the request without a reason', function (): void {
        $form = [
            'marital_status' => 'single', 'activities' => 'Atención.', 'contact_phone' => '4421234567',
            'street' => 'Calle Reforma', 'exterior_number' => '12', 'neighborhood' => 'Centro', 'postal_code' => '76000', 'municipality' => 'Querétaro', 'state' => 'QUE',
        ];
        EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->aspirant->id]);

        $this->post(route('tenant.telework.teleworkers.join', [$this->tenant, $this->aspirant->id]), $form)->assertSessionHasNoErrors();

        expect($this->aspirant->refresh()->status->value)->toBe('active');
    });
});
