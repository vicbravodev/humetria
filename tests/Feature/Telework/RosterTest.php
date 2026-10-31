<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\EndTelework;
use App\Modules\Telework\Actions\JoinRoster;
use App\Modules\Telework\Actions\RegisterAspirant;
use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Data\RosterData;
use App\Modules\Telework\Enums\EndReason;
use App\Modules\Telework\Enums\MaritalStatus;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Http\Controllers\RosterController;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Telework\Support\PortalPending;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(JoinRoster::class, EndTelework::class, RosterController::class);

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

function endUrl(?Teleworker $teleworker = null): string
{
    return route('tenant.telework.teleworkers.end', [test()->tenant, ($teleworker ?? test()->teleworker)->id]);
}

/** @return Teleworker An active period of the test's employee. */
function activePeriod(): Teleworker
{
    // The employee has one open period: the aspirant of beforeEach makes room.
    test()->workplace->delete();
    test()->teleworker->delete();

    return TeleworkerFactory::new()->active()->create(['tenant_id' => test()->tenant->id, 'employee_id' => test()->employee->id, 'work_center_id' => test()->center->id, 'started_at' => '2026-02-01 12:00:00']);
}

it('ends a period with a date, a reason and a note, keeping its workplaces as history', function (EndReason $reason): void {
    $period = activePeriod();
    $place = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $period->id]);

    $this->post(endUrl($period), ['reason' => $reason->value, 'ended_on' => '2026-02-20', 'note' => 'Regresa a la oficina.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.telework.teleworkers.show', [$this->tenant, $period->id]));

    workIn($this->tenant);
    $ended = Teleworker::query()->findOrFail($period->id);

    expect($ended->status)->toBe(TeleworkerStatus::Ended)
        ->and($ended->end_reason)->toBe($reason)
        ->and($ended->end_note)->toBe('Regresa a la oficina.')
        ->and($ended->ended_at?->setTimezone('America/Mexico_City')->toDateString())->toBe('2026-02-20')
        ->and(Workplace::query()->findOrFail($place->id)->retired_at)->toBeNull();
})->with(EndReason::cases());

it('ends an aspirant who never joined', function (): void {
    $this->post(endUrl(), ['reason' => 'other', 'ended_on' => '2026-03-01'])->assertSessionHasNoErrors();

    expect(joined()->status)->toBe(TeleworkerStatus::Ended)->and(joined()->end_note)->toBeNull();
});

it('ends today at the moment it is asked, and an earlier day at its start', function (): void {
    $this->post(endUrl(), ['reason' => 'other', 'ended_on' => '2026-03-01'])->assertSessionHasNoErrors();

    expect(joined()->ended_at?->toIso8601String())->toBe('2026-03-01T12:00:00+00:00');
});

it('keeps the history: an ended period stays and the person can start a new one', function (): void {
    $period = activePeriod();
    $this->post(endUrl($period), ['reason' => 'reversibility', 'ended_on' => '2026-03-01'])->assertSessionHasNoErrors();
    resolve(App\Modules\Tenancy\Contracts\Companies::class)->enter($this->tenant->id);
    ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    $again = resolve(RegisterAspirant::class)->handle($this->employee->id, 60, [new AddressData('Calle Nueva', '1', null, 'Centro', '76000', 'Querétaro', 'QUE')], $this->hr->id);

    expect(Teleworker::query()->where('employee_id', $this->employee->id)->count())->toBe(2)
        ->and(Teleworker::query()->findOrFail($period->id)->status)->toBe(TeleworkerStatus::Ended)
        ->and($again->status)->toBe(TeleworkerStatus::Aspirant);
});

it('does not end a period twice', function (): void {
    $period = TeleworkerFactory::new()->ended(EndReason::Transfer)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    $this->post(endUrl($period), ['reason' => 'other', 'ended_on' => '2026-03-01'])->assertSessionHasErrors('teleworker');

    workIn($this->tenant);
    expect(Teleworker::query()->findOrFail($period->id)->end_reason)->toBe(EndReason::Transfer);
});

it('validates the reason, the date and the note', function (array $input, string $field): void {
    $period = activePeriod();

    $this->post(endUrl($period), $input)->assertSessionHasErrors($field);

    workIn($this->tenant);
    expect(Teleworker::query()->findOrFail($period->id)->status)->toBe(TeleworkerStatus::Active);
})->with([
    'no reason' => [['ended_on' => '2026-03-01'], 'reason'],
    'unknown reason' => [['reason' => 'fired', 'ended_on' => '2026-03-01'], 'reason'],
    'no date' => [['reason' => 'other'], 'ended_on'],
    'not a date' => [['reason' => 'other', 'ended_on' => 'tomorrow-ish'], 'ended_on'],
    'in the future' => [['reason' => 'other', 'ended_on' => '2026-03-02'], 'ended_on'],
    'before it started' => [['reason' => 'other', 'ended_on' => '2026-01-31'], 'ended_on'],
    'too long a note' => [['reason' => 'other', 'ended_on' => '2026-03-01', 'note' => str_repeat('a', 1001)], 'note'],
]);

it('cancels the checklists still waiting when a period ends, and leaves the sent ones', function (): void {
    $pending = ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $sent = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'section' => 2]);

    $this->post(endUrl(), ['reason' => 'other', 'ended_on' => '2026-03-01'])->assertSessionHasNoErrors();

    workIn($this->tenant);
    expect(ChecklistApplication::query()->findOrFail($pending->id)->cancelled_at?->toIso8601String())->toBe('2026-03-01T12:00:00+00:00')
        ->and(ChecklistApplication::query()->findOrFail($sent->id)->cancelled_at)->toBeNull();
});

it('does not show a cancelled checklist as pending in the portal, nor let it be opened', function (): void {
    workIn($this->tenant);
    $application = ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $portal = new OpenedPortal($this->tenant->id, $this->employee->id, $this->teleworker->id, 'Acme', 'Ana Ruiz');

    expect(resolve(PortalPending::class)->for($portal)->checklists)->toHaveCount(1);

    $application->update(['cancelled_at' => now()]);

    expect(resolve(PortalPending::class)->for($portal)->checklists)->toBe([])
        ->and(ChecklistApplication::query()->ownedBy($this->teleworker->id)->count())->toBe(0);
});

it('logs telework.teleworker_ended with ids and the reason, never the note, and audits it', function (): void {
    Log::spy();

    $this->post(endUrl(), ['reason' => 'reversibility', 'ended_on' => '2026-03-01', 'note' => 'Motivo privado de Ana.'])->assertSessionHasNoErrors();

    $expected = ['teleworker_id' => $this->teleworker->id, 'employee_id' => $this->employee->id, 'work_center_id' => $this->center->id, 'reason' => 'reversibility'];
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'telework.teleworker_ended' && $context === $expected)->once();
    $entry = AuditEntry::query()->where('event', 'telework.teleworker_ended')->sole();

    expect($entry->subject_id)->toBe($this->teleworker->id)->and($entry->metadata)->toEqual([...$expected, 'ended_by' => $this->hr->id]);
    expect(json_encode($entry->metadata, JSON_THROW_ON_ERROR))->not->toContain('privado');
});

it('records no user when the system ends a period', function (): void {
    workIn($this->tenant);

    resolve(EndTelework::class)->handle($this->teleworker->refresh(), EndReason::LeftCompany, CarbonImmutable::now(), null, null);

    $entry = AuditEntry::query()->where('event', 'telework.teleworker_ended')->sole();
    expect($entry->metadata)->toHaveKey('ended_by', null)->and(joined()->end_reason)->toBe(EndReason::LeftCompany);
});

it('lets only whoever manages Telework end a period, and answers 404 for another company', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(endUrl(), ['reason' => 'other', 'ended_on' => '2026-03-01'])->assertForbidden();

    $other = Tenant::factory()->create();
    $this->actingAs(memberOf($other, TenantRole::Hr))->post(route('tenant.telework.teleworkers.end', [$other, $this->teleworker->id]), ['reason' => 'other', 'ended_on' => '2026-03-01'])->assertNotFound();
    $this->actingAs($this->hr)->post(route('tenant.telework.teleworkers.end', [$this->tenant, 'not-a-uuid']), ['reason' => 'other', 'ended_on' => '2026-03-01'])->assertNotFound();

    expect(joined()->status)->toBe(TeleworkerStatus::Aspirant);
});

it('shows each workplace with its derived status and expiry, and whether the person can join', function (): void {
    $other = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->teleworker->id, 'street' => 'Otra calle']);
    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, $this->teleworker->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('teleworker.canJoin', false)
            ->where('teleworker.jobTitle', 'Analista')
            ->where('teleworker.workplaces.0.status', 'pending')
            ->where('teleworker.workplaces.0.expiresOn', null));

    rosterReview('plan', '2026-02-10 12:00:00');
    $other->update(['retired_at' => now()]);

    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, $this->teleworker->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('teleworker.canJoin', true)
            ->where('teleworker.workplaces.0.status', 'valid_with_plan')
            ->where('teleworker.workplaces.0.statusLabel', 'Validado con plan de acción')
            ->where('teleworker.workplaces.0.expiresOn', '2027-02-10')
            ->where('teleworker.workplaces.1.status', 'retired'));
});

it('does not offer to join once the period is active or ended, and says how it ended', function (): void {
    $period = TeleworkerFactory::new()->ended(EndReason::Transfer)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'end_note' => 'Se mudó de ciudad.']);

    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, $period->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('teleworker.canJoin', false)
            ->where('teleworker.status', 'ended')
            ->where('teleworker.endReason', 'Cambio de centro de trabajo')
            ->where('teleworker.endNote', 'Se mudó de ciudad.')
            ->has('teleworker.endedOn'));
});

it('labels a cancelled checklist as cancelled, not as pending', function (): void {
    ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'cancelled_at' => now()]);

    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, $this->teleworker->id]))
        ->assertInertia(fn (Assert $page) => $page->where('teleworker.workplaces.0.label', 'Cancelada'));
});

it('puts no individual answers in the props of the page', function (): void {
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'special_needs' => 'Necesito un escritorio alto.']);

    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, $this->teleworker->id]))
        ->assertInertia(fn (Assert $page) => $page->missing('teleworker.workplaces.0.answers')->missing('teleworker.workplaces.0.specialNeeds'));
    expect($this->get(route('tenant.telework.teleworkers.show', [$this->tenant, $this->teleworker->id]))->getContent())->not->toContain('escritorio alto');
});
