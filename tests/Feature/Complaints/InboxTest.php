<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\ViewCase;
use App\Modules\Complaints\Enums\ComplaintResolution;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Http\Controllers\ComplaintController;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\CasePages;
use App\Modules\Complaints\Support\InboxPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(InboxPages::class, CasePages::class, ComplaintController::class, ViewCase::class);

// How many queries the inbox makes: measured, then fixed so that a new N+1 breaks the test.
const QUERIES_OF_THE_INBOX = 17;

const STORY = 'El jefe de turno me humilla frente a todos cada semana';
const NAME = 'Rosa Aguilar Quintero';
const CONTACT = 'rosa.privada@ejemplo.mx';
const ABOUT = 'Don Remigio Salazar';
const INVOLVED = 'Luis Pérez y Marta Gil';

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['acknowledge_within_days' => 3]);
    workIn($this->tenant);
});

/**
 * A complaint assigned to the given people.
 *
 * @param  array<string, mixed>  $attributes
 */
function heldBy(object $test, array $people, array $attributes = []): Complaint
{
    $complaint = Complaint::factory()->create(['tenant_id' => $test->tenant->id, 'channel_id' => $test->channel->id, ...$attributes]);

    foreach ($people as $person) {
        ComplaintAssignment::factory()->create(['tenant_id' => $test->tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $person->id]);
    }

    return $complaint;
}

it('lists to a handler only the complaints assigned to them, the newest day first', function (): void {
    $old = heldBy($this, [$this->owner], ['reference' => 'OLD00000', 'received_on' => '2026-11-10']);
    $new = heldBy($this, [$this->owner], ['reference' => 'NEW00000', 'received_on' => '2026-11-19']);
    heldBy($this, [$this->hr], ['reference' => 'HRS00000']);
    heldBy($this, [], ['reference' => 'NOBODY00']);

    $this->actingAs($this->owner)->get(route('tenant.complaints.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('complaints/index')
            ->has('cases.data', 2)
            ->where('cases.data.0.reference', 'NEW00000')
            ->where('cases.data.1.reference', 'OLD00000')
            ->where('cases.total', 2));

    expect([$new->id, $old->id])->toHaveCount(2);
});

it('shows the open complaints by default and the closed ones apart, with their counts', function (): void {
    heldBy($this, [$this->owner], ['reference' => 'NEW00000']);
    heldBy($this, [$this->owner], ['reference' => 'REV00000', 'status' => 'in_review', 'acknowledged_at' => now()]);
    heldBy($this, [$this->owner], ['reference' => 'CLO00000', 'status' => 'closed', 'resolution' => ComplaintResolution::MeasuresTaken, 'acknowledged_at' => now(), 'closed_at' => now(), 'closed_on' => '2026-11-19']);

    $this->actingAs($this->owner)->get(route('tenant.complaints.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'open')
            ->where('counts', ['open' => 2, 'closed' => 1])
            ->has('cases.data', 2));
    $this->actingAs($this->owner)->get(route('tenant.complaints.index', [$this->tenant, 'status' => 'closed']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'closed')
            ->has('cases.data', 1)
            ->where('cases.data.0.reference', 'CLO00000')
            ->where('cases.data.0.resolution', 'Se tomaron medidas'));
});

it('narrows the list by type, work center and reference', function (): void {
    $plant = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Planta Querétaro']);
    heldBy($this, [$this->owner], ['reference' => 'AAAA1111', 'type' => 'workplace_violence']);
    heldBy($this, [$this->owner], ['reference' => 'BBBB2222', 'type' => 'unfavorable_practice', 'work_center_id' => $plant->id]);

    $reference = fn (array $query) => $this->actingAs($this->owner)->get(route('tenant.complaints.index', [$this->tenant, ...$query]))->viewData('page')['props']['cases']['data'];

    expect(collect($reference(['type' => 'unfavorable_practice']))->pluck('reference')->all())->toBe(['BBBB2222'])
        ->and(collect($reference(['center' => $plant->id]))->pluck('reference')->all())->toBe(['BBBB2222'])
        ->and(collect($reference(['q' => 'aaaa']))->pluck('reference')->all())->toBe(['AAAA1111'])
        ->and(collect($reference(['q' => '1111']))->pluck('reference')->all())->toBe([])
        ->and($reference(['type' => 'nonsense']))->toHaveCount(2)
        ->and(collect($reference(['center' => $plant->id, 'type' => 'workplace_violence']))->pluck('reference')->all())->toBe([]);
});

it('pages the list in the server', function (): void {
    foreach (range(1, 16) as $n) {
        heldBy($this, [$this->owner], ['reference' => sprintf('REF%05d', $n)]);
    }

    $this->actingAs($this->owner)->get(route('tenant.complaints.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->has('cases.data', 15)->where('cases.last_page', 2)->where('cases.total', 16));
    $this->actingAs($this->owner)->get(route('tenant.complaints.index', [$this->tenant, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('cases.data', 1));
});

it('marks overdue the complaints nobody acknowledged within the days the company set', function (string $received, bool $acknowledged, bool $overdue): void {
    heldBy($this, [$this->owner], ['reference' => 'DUE00000', 'received_on' => $received, 'acknowledged_at' => $acknowledged ? now() : null, 'status' => $acknowledged ? 'in_review' : 'new']);

    $this->actingAs($this->owner)->get(route('tenant.complaints.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('cases.data.0.overdue', $overdue));
})->with([
    'received 3 days ago (the limit)' => ['2026-11-17', false, false],
    'received 4 days ago' => ['2026-11-16', false, true],
    'received 4 days ago and acknowledged' => ['2026-11-16', true, false],
    'received today' => ['2026-11-19', false, false],
]);

it('sends no text of a complaint in the list, nor who wrote it', function (): void {
    heldBy($this, [$this->owner], ['description' => STORY, 'respondent_text' => ABOUT, 'reporter_name' => NAME, 'reporter_contact' => CONTACT, 'identity_mode' => 'confidential']);
    heldBy($this, [$this->owner], ['type' => 'traumatic_event_notice', 'description' => STORY.' (aviso)', 'involved_text' => INVOLVED, 'reporter_name' => NAME, 'identity_mode' => 'confidential', 'occurred_on' => '2026-11-01']);

    $page = $this->actingAs($this->owner)->get(route('tenant.complaints.index', $this->tenant))->assertOk();
    $props = json_encode($page->viewData('page')['props']);

    foreach ([STORY, ABOUT, NAME, CONTACT, INVOLVED, 'access_key_hash', 'reporter_', 'description'] as $secret) {
        expect($props)->not->toContain($secret);
    }
});

it('asks the database for the list in a fixed number of queries, however many complaints there are', function (): void {
    $plant = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    foreach (range(1, 12) as $n) {
        heldBy($this, [$this->owner, $this->hr], ['work_center_id' => $plant->id]);
    }
    $this->actingAs($this->owner);

    $this->expectsDatabaseQueryCount(QUERIES_OF_THE_INBOX);

    $this->get(route('tenant.complaints.index', $this->tenant))->assertOk();
});

it('opens the case to an assigned handler, with the text and who is with it, and records that it was read', function (): void {
    Log::spy();
    $plant = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Planta Querétaro']);
    $complaint = heldBy($this, [$this->owner, $this->hr], [
        'reference' => 'CASE0001', 'type' => 'workplace_violence', 'category' => 'mistreatment', 'respondent_kind' => RespondentKind::DirectSupervisor,
        'description' => STORY, 'respondent_text' => ABOUT, 'identity_mode' => 'confidential', 'reporter_name' => NAME, 'reporter_contact' => CONTACT,
        'work_center_id' => $plant->id, 'occurred_on' => '2026-11-05', 'received_on' => '2026-11-18',
    ]);

    $names = [$this->owner->name, $this->hr->name];
    sort($names);

    $this->actingAs($this->owner)->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('complaints/show')
            ->where('case.id', $complaint->id)
            ->where('case.reference', 'CASE0001')
            ->where('case.type', 'workplace_violence')
            ->where('case.typeLabel', 'Denuncia de violencia laboral')
            ->where('case.categoryLabel', 'Malos tratos')
            ->where('case.center', 'Planta Querétaro')
            ->where('case.kindLabel', 'Jefa o jefe inmediato')
            ->where('case.identity', 'confidential')
            ->where('case.reporterName', NAME)
            ->where('case.reporterContact', CONTACT)
            ->where('case.description', STORY)
            ->where('case.respondentText', ABOUT)
            ->where('case.receivedOn', '2026-11-18')
            ->where('case.occurredOn', '2026-11-05')
            ->where('case.status', 'new')
            ->where('case.onHold', false)
            ->where('access', $names)
            ->missing('case.accessKeyHash')
            ->missing('case.access_key_hash'));

    $entry = AuditEntry::query()->where('event', 'complaint.viewed')->sole();
    expect($entry->subject_id)->toBe($complaint->id)->and($entry->actor_id)->toBe($this->owner->id);
    Log::shouldHaveReceived('info')->with('complaints.case_viewed', ['complaint_id' => $complaint->id])->once();
    expect(json_encode($entry->metadata))->not->toContain('Rosa')->not->toContain('jefe');
});

it('gives an anonymous case no field of identity at all', function (): void {
    $complaint = heldBy($this, [$this->owner], ['description' => STORY]);

    $this->actingAs($this->owner)->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('case.identity', 'anonymous')
            ->missing('case.reporterName')
            ->missing('case.reporterContact')
            ->missing('case.reporterEmail'));
});

it('shows the day and the people involved of a notice of a traumatic event', function (): void {
    $complaint = heldBy($this, [$this->owner], ['type' => 'traumatic_event_notice', 'involved_text' => INVOLVED, 'reporter_name' => NAME, 'identity_mode' => 'confidential', 'occurred_on' => '2026-11-01']);

    $this->actingAs($this->owner)->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))
        ->assertInertia(fn (Assert $page) => $page->where('case.involvedText', INVOLVED)->where('case.occurredOn', '2026-11-01'));
});

it('answers 404, never 403, to whoever is not assigned to the case, whatever their role', function (TenantRole $role): void {
    $complaint = heldBy($this, [$this->owner]);
    $person = memberOf($this->tenant, $role);

    $this->actingAs($person)->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))->assertNotFound();
})->with([TenantRole::Hr, TenantRole::Committee, TenantRole::Complaints]);

it('gives the owner no case the owner is not assigned to, although they manage the channel', function (): void {
    $complaint = heldBy($this, [$this->hr]);

    $this->actingAs($this->owner)->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))->assertNotFound();
    expect(AuditEntry::query()->where('event', 'complaint.viewed')->count())->toBe(0);
});

it('answers 404 for a case of another company, one that does not exist and one whose assignment ended', function (): void {
    $theirs = Tenant::factory()->create();
    $channel = ComplaintChannel::factory()->forTenant($theirs->id)->active()->create();
    $foreign = Complaint::factory()->create(['tenant_id' => $theirs->id, 'channel_id' => $channel->id]);
    $ended = heldBy($this, []);
    ComplaintAssignment::factory()->ended()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $ended->id, 'user_id' => $this->owner->id]);

    foreach ([$foreign->id, '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c', $ended->id] as $id) {
        $this->actingAs($this->owner)->get(route('tenant.complaints.cases.show', [$this->tenant, $id]))->assertNotFound();
    }
});

it('lets in the people who can handle complaints and keeps out the rest', function (): void {
    $complaint = heldBy($this, [$this->owner]);
    $outsider = memberOf($this->tenant, TenantRole::Complaints);
    ComplaintAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $outsider->id]);
    $committee = memberOf($this->tenant, TenantRole::Committee);
    ComplaintAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $committee->id]);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($outsider)->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))->assertOk();
    $this->actingAs($committee)->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))->assertOk();
    $this->actingAs($viewer)->get(route('tenant.complaints.index', $this->tenant))->assertForbidden();
    $this->actingAs($viewer)->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))->assertForbidden();
});

it('keeps the case routes closed to a superadmin acting as the owner', function (): void {
    $complaint = heldBy($this, [$this->owner]);
    $this->actingAs(superadmin())->post(route('companies.impersonate', $this->tenant));

    $this->get(route('tenant.complaints.index', $this->tenant))->assertNotFound();
    $this->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))->assertNotFound();
    expect(AuditEntry::query()->where('event', 'complaint.viewed')->count())->toBe(0);
});

it('guards every route that opens or changes a case against impersonation', function (): void {
    $names = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->getName())->filter(fn (?string $name): bool => $name !== null && str_starts_with($name, 'tenant.complaints.'));
    $caseRoutes = $names->reject(fn (string $name): bool => str_starts_with($name, 'tenant.complaints.channel.') || str_starts_with($name, 'tenant.complaints.handlers.') || str_starts_with($name, 'tenant.complaints.unassigned.') || str_starts_with($name, 'tenant.complaints.centers.') || str_starts_with($name, 'tenant.complaints.poster') || str_starts_with($name, 'tenant.complaints.evidence'));

    expect($caseRoutes->isNotEmpty())->toBeTrue();
    foreach ($caseRoutes as $name) {
        expect(App\Http\Middleware\ForbidWhileImpersonating::guards($name))->toBeTrue("{$name} is not guarded");
    }
});

it('shows a viewer none of the complaints in the sidebar and a handler outside the company\'s tools only the channel', function (): void {
    $outsider = memberOf($this->tenant, TenantRole::Complaints);

    $this->actingAs($outsider)->get(route('tenant.complaints.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('inCourse', null)->where('tenant.can.handle_complaints', true)->where('tenant.can.manage_complaint_channel', false));
});

it('searches the work centers for the filter of the inbox, for any handler, and for nobody else', function (): void {
    WorkCenter::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Planta Querétaro']);
    WorkCenter::factory()->create(['name' => 'Planta Querétaro Ajena']);
    $outsider = memberOf($this->tenant, TenantRole::Complaints);

    $options = $this->actingAs($outsider)->getJson(route('tenant.complaints.centers.options', [$this->tenant, 'q' => 'quer']))->assertOk()->json();

    expect(array_column($options, 'label'))->toBe(['Planta Querétaro']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->getJson(route('tenant.complaints.centers.options', $this->tenant))->assertForbidden();
});

it('tells the inbox which center it is filtered by, by name', function (): void {
    $plant = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Planta Querétaro']);
    heldBy($this, [$this->owner], ['work_center_id' => $plant->id]);

    $this->actingAs($this->owner)->get(route('tenant.complaints.index', [$this->tenant, 'center' => $plant->id, 'type' => 'workplace_violence', 'q' => ' abc ']))
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['type' => 'workplace_violence', 'center' => ['value' => $plant->id, 'label' => 'Planta Querétaro'], 'q' => 'abc']));
});
