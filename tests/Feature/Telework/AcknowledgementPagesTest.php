<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\Acknowledge;
use App\Modules\Telework\Actions\RecordManualAcknowledgements;
use App\Modules\Telework\Enums\AckChannel;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Http\Controllers\AcknowledgementController;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\AckAudience;
use App\Modules\Telework\Support\AckProgress;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AckProgress::class, AckAudience::class, RecordManualAcknowledgements::class, AcknowledgementController::class);

beforeEach(function (): void {
    Queue::fake();
    Storage::fake(config()->string('filesystems.telework_files'));
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->other = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
});

function worker(string $name, ?WorkCenter $center = null, array $state = []): Employee
{
    return EmployeeFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => ($center ?? test()->center)->id, 'name' => $name, ...$state]);
}

function published(DocumentKind $kind = DocumentKind::Policy, ?WorkCenter $center = null): TeleworkDocument
{
    $path = tempnam(sys_get_temp_dir(), 'doc');
    file_put_contents($path, "%PDF-1.4\n%%EOF\n");

    return resolve(App\Modules\Telework\Actions\PublishDocument::class)->handle($kind, $path, [($center ?? test()->center)->id], [], test()->hr->id)[0];
}

function confirm(TeleworkDocument $document, Employee $employee): void
{
    Acknowledgement::query()->where('document_id', $document->id)->where('employee_id', $employee->id)->update(['channel' => 'portal', 'acknowledged_at' => now()]);
}

it('counts the percentage over today\'s active staff: someone dismissed no longer counts', function (): void {
    $ana = worker('Ana');
    $bea = worker('Bea');
    $cleo = worker('Cleo');
    $document = published();
    confirm($document, $ana);
    confirm($document, $cleo);
    expect(resolve(AckProgress::class)->of($document))->toBe(['acknowledged' => 2, 'expected' => 3]);

    $cleo->forceFill(['deactivated_at' => now()])->save();
    $dina = worker('Dina');

    // Cleo left (not expected, not counted); Dina arrived without a request (expected, not yet acknowledged).
    expect(resolve(AckProgress::class)->of($document))->toBe(['acknowledged' => 1, 'expected' => 3])
        ->and($bea->id)->not->toBe($dina->id);
});

it('counts the roster and the committee for risk information, and ignores older versions', function (): void {
    $onRoster = worker('Rosa');
    TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $onRoster->id]);
    worker('Aspirante');
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    $first = published(DocumentKind::RiskInformation);
    $second = published(DocumentKind::RiskInformation);
    confirm($first, $onRoster);
    resolve(Acknowledge::class)->inPanel($cora->id, $second->id, null);

    expect(resolve(AckProgress::class)->of($first))->toBe(['acknowledged' => 1, 'expected' => 2])
        ->and(resolve(AckProgress::class)->of($second))->toBe(['acknowledged' => 1, 'expected' => 2]);

    CommitteeAssignment::query()->where('user_id', $cora->id)->delete();
    expect(resolve(AckProgress::class)->of($second))->toBe(['acknowledged' => 0, 'expected' => 1]);
});

it('has no progress for the kinds that need no acknowledgement', function (): void {
    worker('Ana');

    expect(resolve(AckProgress::class)->of(published(DocumentKind::Reversibility)))->toBe(['acknowledged' => 0, 'expected' => 0]);
});

it('acknowledges in the panel as a Comisión member, once, and only what was asked of them', function (): void {
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    $document = published(DocumentKind::RiskInformation);
    $policy = published();
    $this->actingAs($cora);

    $this->post(route('tenant.telework.documents.acknowledge', [$this->tenant, $document->id]))->assertSessionHasNoErrors()->assertRedirect(route('tenant.telework.documents.index', $this->tenant));
    $this->post(route('tenant.telework.documents.acknowledge', [$this->tenant, $document->id]))->assertSessionHasErrors('document');
    $this->post(route('tenant.telework.documents.acknowledge', [$this->tenant, $policy->id]))->assertNotFound();

    $request = Acknowledgement::query()->where('user_id', $cora->id)->sole();
    expect($request->channel)->toBe(AckChannel::Panel)->and($request->acknowledged_at)->not->toBeNull();
});

it('shows the progress on each document of the list, and none for the other kinds', function (): void {
    $ana = worker('Ana');
    worker('Bea');
    confirm(published(), $ana);
    published(DocumentKind::Reversibility);
    $this->actingAs($this->hr);

    $this->get(route('tenant.telework.documents.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->has('documents.data', 2)
        ->where('documents.data', fn ($rows) => collect($rows)->contains(fn ($row): bool => $row['kind'] === 'policy' && $row['acknowledgements'] === ['acknowledged' => 1, 'expected' => 2])
            && collect($rows)->contains(fn ($row): bool => $row['kind'] === 'reversibility' && $row['acknowledgements'] === null)));
});

it('lists who confirmed and who is pending, a page at a time, with a status filter and a name search', function (): void {
    $ana = worker('Ana Ruiz');
    worker('Beto Soto');
    worker('Carla Soto');
    worker('Elsewhere', $this->other);
    $document = published();
    confirm($document, $ana);
    $this->actingAs($this->hr);
    $url = fn (array $query = []) => route('tenant.telework.documents.acknowledgements.index', [$this->tenant, $document->id, ...$query]);

    $this->get($url())->assertInertia(fn (Assert $page) => $page
        ->component('telework/acknowledgements')
        ->where('document.title', 'Política de Teletrabajo · versión 1')
        ->where('document.workCenter', 'Planta Norte')
        ->where('progress', ['acknowledged' => 1, 'expected' => 3])
        ->where('people.total', 3)
        ->where('people.per_page', 15)
        ->where('counts', ['all' => 3, 'pending' => 2, 'acknowledged' => 1])
        ->where('committee', [])
        ->where('canManage', true)
        ->where('canAcknowledge', false)
        ->where('filters', ['status' => 'all', 'q' => ''])
        ->where('people.data.0', fn ($row) => $row['name'] === 'Ana Ruiz' && $row['status'] === 'acknowledged' && $row['channel'] === 'portal' && $row['employeeId'] === $ana->id)
        ->missing('matching'));
    $this->get($url(['status' => 'pending']))->assertInertia(fn (Assert $page) => $page
        ->where('people.total', 2)->where('people.data.0.name', 'Beto Soto')->where('people.data.0.status', 'pending')->where('filters.status', 'pending'));
    $this->get($url(['status' => 'acknowledged']))->assertInertia(fn (Assert $page) => $page->where('people.total', 1));
    $this->get($url(['q' => 'sóto']))->assertInertia(fn (Assert $page) => $page->where('people.total', 2)->where('filters.q', 'sóto'));
    $this->get($url(['status' => 'pending', 'q' => 'soto']))->assertInertia(fn (Assert $page) => $page->where('people.total', 2));
    $this->get($url(['status' => 'bogus']))->assertInertia(fn (Assert $page) => $page->where('filters.status', 'all'));
});

it('hands over the pending people the search found only when asked, to tick them all', function (): void {
    worker('Ana Ruiz');
    $beto = worker('Beto Soto');
    $document = published();
    confirm($document, $beto);
    $this->actingAs($this->hr);

    $this->get(route('tenant.telework.documents.acknowledgements.index', [$this->tenant, $document->id, 'status' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('matching', fn (Assert $reload) => $reload->where('matching', [Employee::query()->where('name', 'Ana Ruiz')->value('id')])));
});

it('never puts the address, the note or the channel of someone else in the props beyond the channel', function (): void {
    $ana = worker('Ana Ruiz');
    $document = published();
    resolve(RecordManualAcknowledgements::class)->handle($document->id, [$ana->id], CarbonImmutable::parse('2026-05-04'), 'Firmó en papel', $this->hr->id);
    Acknowledgement::query()->update(['ip' => '203.0.113.9']);
    $this->actingAs($this->hr);

    $response = $this->get(route('tenant.telework.documents.acknowledgements.index', [$this->tenant, $document->id]));

    expect($response->getContent())->not->toContain('203.0.113.9')->and($response->getContent())->not->toContain('Firmó en papel');
});

it('keeps the list inside reach: another company, an unseen center and a committee member without the seat get a 404', function (): void {
    $document = published();
    $elsewhere = published(DocumentKind::Policy, $this->other);
    $foreign = Tenant::factory()->create();
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($cora);
    $this->get(route('tenant.telework.documents.acknowledgements.index', [$this->tenant, $document->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->get(route('tenant.telework.documents.acknowledgements.index', [$this->tenant, $elsewhere->id]))->assertNotFound();
    $this->actingAs($viewer);
    $this->get(route('tenant.telework.documents.acknowledgements.index', [$this->tenant, $elsewhere->id]))->assertOk();
    $this->actingAs($this->hr);
    $this->get(route('tenant.telework.documents.acknowledgements.index', [$foreign, $document->id]))->assertNotFound();
    $this->get(route('tenant.telework.documents.acknowledgements.index', [$this->tenant, 'not-a-uuid']))->assertNotFound();
    $this->get(route('tenant.telework.documents.acknowledgements.index', [$this->tenant, Illuminate\Support\Str::uuid()->toString()]))->assertNotFound();
});

it('records paper acknowledgements in bulk with their date and note, only for those still pending', function (): void {
    $ana = worker('Ana');
    $bea = worker('Bea');
    $cleo = worker('Cleo');
    $stranger = worker('Zoe', $this->other);
    $document = published();
    confirm($document, $cleo);
    Log::spy();

    $count = resolve(RecordManualAcknowledgements::class)->handle($document->id, [$ana->id, $bea->id, $cleo->id, $stranger->id], CarbonImmutable::parse('2026-05-04 00:00:00', 'America/Mexico_City'), 'Firmas en la hoja 12', $this->hr->id);

    expect($count)->toBe(2);
    $ana = Acknowledgement::query()->where('employee_id', $ana->id)->sole();
    expect($ana->channel)->toBe(AckChannel::Manual)
        ->and($ana->acknowledged_at?->toIso8601String())->toBe('2026-05-04T06:00:00+00:00')
        ->and($ana->note)->toBe('Firmas en la hoja 12')
        ->and($ana->recorded_by)->toBe($this->hr->id)
        ->and($ana->ip)->toBeNull()
        ->and(Acknowledgement::query()->where('employee_id', $cleo->id)->sole()->channel)->toBe(AckChannel::Portal)
        ->and(Acknowledgement::query()->where('employee_id', $stranger->id)->count())->toBe(0);
    $entry = AuditEntry::query()->where('event', 'telework.acknowledgements_recorded')->sole();
    expect($entry->subject_id)->toBe($document->id)
        ->and($entry->metadata)->toEqual(['document_id' => $document->id, 'employee_ids' => collect([$ana->employee_id, $bea->id])->sort()->values()->all(), 'count' => 2, 'recorded_by' => $this->hr->id]);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'telework.acknowledged'
        && $context === ['document_id' => $document->id, 'employee_ids' => collect([$ana->employee_id, $bea->id])->sort()->values()->all(), 'channel' => 'manual'] && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'hoja'))->once();
});

it('records nothing and audits nothing when nobody is pending', function (): void {
    $ana = worker('Ana');
    $document = published();
    confirm($document, $ana);

    expect(resolve(RecordManualAcknowledgements::class)->handle($document->id, [$ana->id], CarbonImmutable::now(), 'Nota', $this->hr->id))->toBe(0)
        ->and(AuditEntry::query()->where('event', 'telework.acknowledgements_recorded')->count())->toBe(0);
});

it('records paper acknowledgements from the page', function (): void {
    $ana = worker('Ana');
    $bea = worker('Bea');
    $document = published();
    $this->actingAs($this->hr);
    $url = route('tenant.telework.documents.acknowledgements.store', [$this->tenant, $document->id]);

    $this->post($url, ['employee_ids' => [$ana->id], 'date' => '2026-05-04', 'note' => 'Hoja firmada'])->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.telework.documents.acknowledgements.index', [$this->tenant, $document->id]));

    expect(Acknowledgement::query()->where('employee_id', $ana->id)->sole()->acknowledged_at?->toIso8601String())->toBe('2026-05-04T06:00:00+00:00')
        ->and(Acknowledgement::query()->where('employee_id', $bea->id)->sole()->acknowledged_at)->toBeNull();
});

it('refuses a paper record without people, a date or a note, or with a future date', function (array $form, string $field): void {
    $ana = worker('Ana');
    $document = published();
    $this->actingAs($this->hr);
    $valid = ['employee_ids' => [$ana->id], 'date' => '2026-05-04', 'note' => 'Hoja'];

    $this->post(route('tenant.telework.documents.acknowledgements.store', [$this->tenant, $document->id]), [...$valid, ...$form])->assertSessionHasErrors($field);

    expect(Acknowledgement::query()->whereNotNull('acknowledged_at')->count())->toBe(0);
})->with([
    'no people' => [['employee_ids' => []], 'employee_ids'],
    'a bad id' => [['employee_ids' => ['x']], 'employee_ids.0'],
    'no date' => [['date' => ''], 'date'],
    'a future date' => [['date' => '2999-01-01'], 'date'],
    'no note' => [['note' => ''], 'note'],
    'a long note' => [['note' => str_repeat('x', 501)], 'note'],
]);

it('lets only whoever manages Telework record paper acknowledgements', function (TenantRole $role): void {
    $ana = worker('Ana');
    $document = published();
    $this->actingAs(memberOf($this->tenant, $role));

    $this->post(route('tenant.telework.documents.acknowledgements.store', [$this->tenant, $document->id]), ['employee_ids' => [$ana->id], 'date' => '2026-05-04', 'note' => 'Hoja'])->assertForbidden();

    expect(Acknowledgement::query()->whereNotNull('acknowledged_at')->count())->toBe(0);
})->with([TenantRole::Viewer, TenantRole::Committee]);
