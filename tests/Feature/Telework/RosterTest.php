<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\JoinRoster;
use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Data\RosterData;
use App\Modules\Telework\Enums\MaritalStatus;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Http\Controllers\RosterController;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(JoinRoster::class, RosterController::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz', 'job_title' => 'Analista']);
    $this->teleworker = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    $this->workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id]);
    $this->actingAs($this->hr);
});

/** A review of the test's workplace, or of `$workplace`. */
function rosterReview(string $state = 'valid', string $at = '2026-02-01 12:00:00'): void
{
    $factory = WorkplaceReviewFactory::new()->decidedAt($at);
    $factory = match ($state) {
        'not_valid' => $factory->notValid(),
        'plan' => $factory->withPlan(),
        default => $factory,
    };
    $factory->create(['tenant_id' => test()->tenant->id, 'workplace_id' => test()->workplace->id]);
}

/** @return array<string, string> The 5.1 data a person gives when joining, the job profile coming from the employee. */
function rosterForm(array $overrides = []): array
{
    return [
        'marital_status' => 'single',
        'activities' => 'Atención de clientes por correo y llamadas.',
        'contact_phone' => '4421234567',
        'street' => 'Calle Reforma',
        'exterior_number' => '12',
        'interior_number' => '',
        'neighborhood' => 'Centro',
        'postal_code' => '76000',
        'municipality' => 'Querétaro',
        'state' => 'QUE',
        'no_equipment_reason' => 'La persona usa su propio equipo.',
        ...$overrides,
    ];
}

function rosterData(array $overrides = []): RosterData
{
    $fields = [
        'maritalStatus' => MaritalStatus::Single,
        'activities' => 'Atención de clientes.',
        'jobProfile' => 'Analista',
        'contactPhone' => '4421234567',
        'home' => new AddressData('Calle Reforma', '12', null, 'Centro', '76000', 'Querétaro', 'QUE'),
        'noEquipmentReason' => 'Usa su propio equipo.',
        ...$overrides,
    ];

    return new RosterData(...$fields);
}

function joinUrl(?Teleworker $teleworker = null): string
{
    return route('tenant.telework.teleworkers.join', [test()->tenant, ($teleworker ?? test()->teleworker)->id]);
}

function joined(): Teleworker
{
    workIn(test()->tenant);

    return Teleworker::query()->findOrFail(test()->teleworker->id);
}

it('joins the roster with the eleven data of 5.1 once a workplace is in force', function (string $state): void {
    rosterReview($state);

    $this->post(joinUrl(), rosterForm())->assertSessionHasNoErrors()->assertRedirect(route('tenant.telework.teleworkers.show', [$this->tenant, $this->teleworker->id]));

    $teleworker = joined();

    expect($teleworker->status)->toBe(TeleworkerStatus::Active)
        ->and($teleworker->started_at?->toIso8601String())->toBe('2026-03-01T12:00:00+00:00')
        ->and($teleworker->marital_status)->toBe(MaritalStatus::Single)
        ->and($teleworker->activities)->toBe('Atención de clientes por correo y llamadas.')
        ->and($teleworker->job_profile)->toBe('Analista')
        ->and($teleworker->contact_phone)->toBe('4421234567')
        ->and([$teleworker->street, $teleworker->exterior_number, $teleworker->interior_number, $teleworker->neighborhood, $teleworker->postal_code, $teleworker->municipality, $teleworker->state])
        ->toBe(['Calle Reforma', '12', null, 'Centro', '76000', 'Querétaro', 'QUE'])
        ->and($teleworker->no_equipment_reason)->toBe('La persona usa su propio equipo.');
})->with(['valid' => 'valid', 'valid with plan' => 'plan']);

it('takes the job profile from the employee, never from the request', function (): void {
    rosterReview();

    $this->post(joinUrl(), rosterForm(['job_profile' => 'Director general']))->assertSessionHasNoErrors();

    expect(joined()->job_profile)->toBe('Analista');
});

it('refuses to join without each of the data of 5.1', function (string $field, string $key): void {
    rosterReview();
    workIn($this->tenant);

    $data = match ($field) {
        'maritalStatus', 'activities', 'jobProfile', 'contactPhone', 'home', 'noEquipmentReason' => rosterData([$field => null]),
        'blankActivities' => rosterData(['activities' => "  \n "]),
        'blankPhone' => rosterData(['contactPhone' => '']),
        'blankReason' => rosterData(['noEquipmentReason' => '  ']),
        default => rosterData(['home' => new AddressData(...[...['street' => 'Calle Reforma', 'exteriorNumber' => '12', 'interiorNumber' => null, 'neighborhood' => 'Centro', 'postalCode' => '76000', 'municipality' => 'Querétaro', 'state' => 'QUE'], $field => ''])]),
    };

    try {
        resolve(JoinRoster::class)->handle($this->teleworker->refresh(), $data, $this->hr->id);
        $this->fail('Joining without the data should have been refused.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toContain($key);
    }

    expect($this->teleworker->refresh()->status)->toBe(TeleworkerStatus::Aspirant);
})->with([
    'marital status' => ['maritalStatus', 'marital_status'],
    'activities' => ['activities', 'activities'],
    'blank activities' => ['blankActivities', 'activities'],
    'job profile' => ['jobProfile', 'job_profile'],
    'contact phone' => ['contactPhone', 'contact_phone'],
    'blank phone' => ['blankPhone', 'contact_phone'],
    'home address' => ['home', 'street'],
    'street' => ['street', 'street'],
    'exterior number' => ['exteriorNumber', 'exterior_number'],
    'neighborhood' => ['neighborhood', 'neighborhood'],
    'postal code' => ['postalCode', 'postal_code'],
    'municipality' => ['municipality', 'municipality'],
    'state' => ['state', 'state'],
    'equipment or the reason for none' => ['noEquipmentReason', 'no_equipment_reason'],
    'blank reason' => ['blankReason', 'no_equipment_reason'],
]);

it('reports the missing data on the form', function (string $field): void {
    rosterReview();

    $this->post(joinUrl(), rosterForm([$field => '']))->assertSessionHasErrors($field);

    expect(joined()->status)->toBe(TeleworkerStatus::Aspirant);
})->with(['marital_status', 'activities', 'contact_phone', 'street', 'exterior_number', 'neighborhood', 'postal_code', 'municipality', 'state', 'no_equipment_reason']);

it('refuses a marital status or state that does not exist', function (string $field, string $value): void {
    rosterReview();

    $this->post(joinUrl(), rosterForm([$field => $value]))->assertSessionHasErrors($field);
})->with([['marital_status', 'complicated'], ['state', 'XXX'], ['postal_code', '7600']]);

it('requires at least one workplace in force', function (?string $state, ?string $at, bool $retired): void {
    if ($state !== null) {
        rosterReview($state, $at ?? '2026-02-01 12:00:00');
    }

    if ($retired) {
        $this->workplace->update(['retired_at' => now()]);
    }

    $this->post(joinUrl(), rosterForm())->assertSessionHasErrors('workplaces');

    expect(joined()->status)->toBe(TeleworkerStatus::Aspirant);
})->with([
    'never reviewed' => [null, null, false],
    'not valid' => ['not_valid', null, false],
    'validation lapsed (12 months)' => ['valid', '2025-03-01 12:00:00', false],
    'valid but retired' => ['valid', null, true],
]);

it('joins when one workplace is in force though another is not', function (): void {
    WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'street' => 'Otra calle']);
    rosterReview();

    $this->post(joinUrl(), rosterForm())->assertSessionHasNoErrors();

    expect(joined()->status)->toBe(TeleworkerStatus::Active);
});

it('joins on the last day a validation holds, and not the day after', function (): void {
    rosterReview('valid', '2025-03-02 18:00:00');
    $this->travelTo('2026-03-01 23:00:00');
    $this->post(joinUrl(), rosterForm())->assertSessionHasNoErrors();
    expect(joined()->status)->toBe(TeleworkerStatus::Active);
});

it('does not join twice or after ending', function (string $state): void {
    rosterReview();
    $period = $state === 'active'
        ? TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])
        : TeleworkerFactory::new()->ended()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $period->id]);

    $this->post(joinUrl($period), rosterForm())->assertSessionHasErrors('teleworker');
})->with(['active', 'ended']);

it('logs telework.teleworker_joined with ids only and audits it', function (): void {
    rosterReview();
    Log::spy();

    $this->post(joinUrl(), rosterForm())->assertSessionHasNoErrors();

    $expected = ['teleworker_id' => $this->teleworker->id, 'employee_id' => $this->employee->id, 'work_center_id' => $this->center->id];
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'telework.teleworker_joined' && $context === $expected)->once();
    $entry = AuditEntry::query()->where('event', 'telework.teleworker_joined')->sole();

    expect($entry->subject_id)->toBe($this->teleworker->id)->and($entry->metadata)->toEqual([...$expected, 'joined_by' => $this->hr->id]);
    foreach (['Ana', 'Reforma', '4421234567', 'propio equipo'] as $private) {
        expect(json_encode($entry->metadata, JSON_THROW_ON_ERROR))->not->toContain($private);
    }
});

it('lets only whoever manages Telework join someone', function (TenantRole $role): void {
    rosterReview();

    $this->actingAs(memberOf($this->tenant, $role))->post(joinUrl(), rosterForm())->assertForbidden();

    expect(joined()->status)->toBe(TeleworkerStatus::Aspirant);
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('answers 404, not 403, for a period of another company or one that does not exist', function (): void {
    $other = Tenant::factory()->create();
    $mine = memberOf($other, TenantRole::Hr);

    $this->actingAs($mine)->post(route('tenant.telework.teleworkers.join', [$other, $this->teleworker->id]), rosterForm())->assertNotFound();
    $this->post(route('tenant.telework.teleworkers.join', [$this->tenant, 'not-a-uuid']), rosterForm())->assertNotFound();

    expect(joined()->status)->toBe(TeleworkerStatus::Aspirant);
});

it('refuses in the database an active period without the data of 5.1', function (): void {
    expect(fn () => DB::transaction(fn () => TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'status' => TeleworkerStatus::Active, 'started_at' => now()])))
        ->toThrow(QueryException::class);
});
