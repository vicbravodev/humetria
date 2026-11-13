<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Controllers\TenantDashboardController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\DashboardPage;
use App\Modules\Tenancy\Support\Nom035Route;
use App\Modules\Tenancy\Support\PanelUrgencies;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(TenantDashboardController::class, DashboardPage::class, Nom035Route::class, PanelUrgencies::class);

// Rediseño v2 (board V2-Panel): the panel became «Atiende primero», «Dónde vas» by standard and center, the next step and
// «Esta semana». The evaluation in course, its steps, the two-year cycle and the closed evaluations it showed before now live
// in the NOM-035 line of each center and in NOM-035's own pages.

beforeEach(function (): void {
    // Friday 30 Oct 2026, noon in Mexico City.
    Carbon::setTestNow('2026-10-30 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
});

/** `$count` people of `$campaign` given `$instrument`, in `$status`. */
function panelGuides(Campaign $campaign, string $instrument, InstrumentStatus $status, int $count): void
{
    foreach (range(1, $count) as $ignored) {
        ParticipantInstrument::factory()->create([
            'participant_id' => Participant::factory()->create(['campaign_id' => $campaign->id])->id,
            'instrument' => $instrument,
            'status' => $status,
            'completed_at' => $status === InstrumentStatus::Completed ? now() : null,
        ]);
    }
}

it('reminds a company without active work centers to add one', function (string $case): void {
    WorkCenter::factory()->create(); // Another company's never counts.

    if ($case === 'only archived') {
        WorkCenter::factory()->for($this->tenant)->archived()->create();
    }

    $this->actingAs(ownerOf($this->tenant))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')->where('hasWorkCenters', false)->where('centers.total', 0));
})->with(['none', 'only archived']);

it('dates the greeting and counts the standards that apply in how many centers', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    WorkCenter::factory()->for($this->tenant)->create();
    $employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id]);
    TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id]);
    // Someone works standing there: Ley Silla applies once the center says so (A2).
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id]);

    $this->actingAs(ownerOf($this->tenant))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('today', 'Viernes 30 de octubre')
            ->where('hasWorkCenters', true)
            ->where('summary', ['norms' => 3, 'centers' => 2]));
});

it('pages each work center in use with where it stands in each route', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    WorkCenter::factory()->for($this->tenant)->archived()->create();
    staffOf($this->tenant, $plant, 20);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'closed_at' => '2026-10-29 18:00:00']);

    $this->actingAs(ownerOf($this->tenant))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('centers.total', 1)
            ->where('centers.data.0', [
                'id' => $plant->id,
                'name' => 'Planta Querétaro',
                // Evaluated, nobody to channel and no Programa needed; no policy, results not shared, report not filled in.
                'nom035' => ['applies' => true, 'done' => [false, true, true, false, true, false], 'status' => 'define tu política de prevención', 'answered' => true],
                // Rediseño v2 (decisión A2): nobody teleworks nor works standing there, and it has not answered «¿Te aplica?».
                'nom037' => ['applies' => false, 'done' => [], 'status' => 'Falta contestar', 'answered' => false],
                'seating' => ['applies' => false, 'done' => [], 'status' => 'Falta contestar', 'answered' => false],
            ]));
});

it('tells how the evaluation in course goes, measured against the sample of a Guía III that takes one', function (): void {
    $offices = WorkCenter::factory()->for($this->tenant)->create();
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $offices->id]);
    $campaign = Campaign::factory()->active('2026-11-06')->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $offices->id, 'instruments' => ['nom035.guide-i', 'nom035.guide-iii'], 'population' => 'sample', 'workers' => 100, 'sample_size' => 80, 'quotas' => ['female' => 80]]);
    panelGuides($campaign, 'nom035.guide-iii', InstrumentStatus::Completed, 2);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('centers.data.0.nom035.status', '2 de 80 contestaron, cierra en 7 días'));
});

it('keeps the channeling unseen for whoever may not see the Guía I (num. 5.5): its stage reads as done', function (TenantRole $role, bool $done): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'closed_at' => '2026-10-29 18:00:00']);
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $closed->id, 'requires_clinical_assessment' => true]);

    $this->actingAs(memberOf($this->tenant, $role))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('centers.data.0.nom035.done.2', $done));
})->with([
    'HR, who channels them' => [TenantRole::Hr, false],
    'a viewer' => [TenantRole::Viewer, true],
]);

it('puts first what is urgent, for whoever may act on it, and never what a complaint says', function (TenantRole $role, array $norms): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $person = memberOf($this->tenant, $role);
    $employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'name' => 'Hugo Saiz']);
    $teleworker = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id]);
    $notice = WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $teleworker->id, 'submitted_at' => '2026-02-27 18:00:00']);
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'closed_at' => '2026-10-29 18:00:00']);
    TraumaScreening::factory()->count(9)->create(['campaign_id' => $closed->id, 'requires_clinical_assessment' => true]);
    $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['acknowledge_within_days' => 1]);
    $complaint = Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $channel->id, 'received_on' => '2026-10-30']);
    ComplaintAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $person->id]);
    $urgent = [
        'NOM-037' => ['norm' => 'NOM-037', 'title' => 'Hugo Saiz avisó un accidente de trabajo', 'detail' => 'Planta Querétaro · espera desde el 27 feb 2026', 'action' => 'Revisar aviso', 'href' => route('tenant.telework.notices.show', [$this->tenant, $notice->id], false)],
        'NOM-035' => ['norm' => 'NOM-035', 'title' => '9 personas esperan valoración clínica', 'detail' => 'Planta Querétaro · Guía I · canalízalas a atención', 'action' => 'Canalizar', 'href' => route('tenant.campaigns.screenings', [$this->tenant, $closed->id], false)],
        'Quejas' => ['norm' => 'Quejas', 'title' => 'Una denuncia espera acuse de recibo', 'detail' => 'Vence mañana · la ves porque te designaron · sin contenido aquí', 'action' => 'Acusar recibo', 'href' => route('tenant.complaints.cases.show', [$this->tenant, $complaint->id], false)],
    ];

    $response = $this->actingAs($person)->get(route('tenant.dashboard', $this->tenant));

    $response->assertInertia(fn (Assert $page) => $page->where('urgencies', array_map(fn (string $norm): array => $urgent[$norm], $norms)));
    expect(json_encode($response->viewData('page')['props']))->not->toContain('compañera')->not->toContain('espalda');
})->with([
    'owner' => [TenantRole::Owner, ['NOM-037', 'NOM-035', 'Quejas']],
    'viewer' => [TenantRole::Viewer, []],
]);

it('puts among the urgent the vacation requests that wait for an answer, for whoever manages vacations', function (int $waiting, TenantRole $role, ?string $title): void {
    foreach ($waiting === 0 ? [] : range(1, $waiting) as $ignored) {
        App\Modules\Leave\Models\LeaveRequest::factory()->escalated()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id])->id]);
    }

    // Those that still wait for the manager are not RH's to answer: «Por atender» does not hold them either.
    App\Modules\Leave\Models\LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id])->id]);

    // Answered requests do not wait.
    App\Modules\Leave\Models\LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id])->id]);

    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $title === null
            ? $page->where('urgencies', [])
            : $page->where('urgencies.0', ['norm' => 'Vacaciones', 'title' => $title, 'detail' => 'RH responde cuando el jefe no pudo o no lo hizo a tiempo', 'action' => 'Responder', 'href' => route('tenant.vacations.requests.index', $this->tenant, false)]));
})->with([
    'three, for the owner' => [3, TenantRole::Owner, '3 solicitudes de vacaciones esperan respuesta'],
    'one, for hr' => [1, TenantRole::Hr, '1 solicitud de vacaciones espera respuesta'],
    'none' => [0, TenantRole::Owner, null],
    'not for a viewer' => [3, TenantRole::Viewer, null],
]);

it('leaves the vacation requests out of the panel of a company without the module', function (): void {
    resolve(App\Modules\Tenancy\Contracts\CompanyModules::class)->syncPlan($this->tenant->id, ['nom035'], now()->toImmutable());
    App\Modules\Leave\Models\LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id])->id]);

    $this->actingAs(ownerOf($this->tenant))->get(route('tenant.dashboard', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('urgencies', []));
});

it('tells when the acknowledgment of a complaint falls due, and gathers the accidents and the people to channel', function (string $received, string $when): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $owner = ownerOf($this->tenant);

    foreach (['Ana Ruiz', 'Luis Peña'] as $name) {
        $employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'name' => $name]);
        WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id])->id]);
    }

    $closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'closed_at' => '2026-10-29 18:00:00']);
    TraumaScreening::factory()->create(['campaign_id' => $closed->id, 'requires_clinical_assessment' => true]);
    $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['acknowledge_within_days' => 2]);
    $complaint = Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $channel->id, 'received_on' => $received]);
    ComplaintAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('urgencies.0.title', '2 personas avisaron un accidente de trabajo')
            ->where('urgencies.1.title', '1 persona espera valoración clínica')
            ->where('urgencies.1.detail', 'Planta Querétaro · Guía I · canalízala a atención')
            ->where('urgencies.2.detail', "{$when} · la ves porque te designaron · sin contenido aquí"));
})->with([
    'today' => ['2026-10-28', 'Vence hoy'],
    'later' => ['2026-10-31', 'Vence el 2 nov 2026'],
    'overdue' => ['2026-10-20', 'Venció el 22 oct 2026'],
]);

it('lists what falls due this week: the evaluations that close in the next seven days', function (): void {
    $offices = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey']);
    Campaign::factory()->active('2026-11-04')->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $offices->id]);
    Campaign::factory()->active('2026-11-30')->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]);

    $this->actingAs(ownerOf($this->tenant))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('week', [['day' => 'Mié 4 nov', 'text' => 'cierra la evaluación NOM-035 de Oficinas Monterrey']]));
});

it('never carries an answer to the panel', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id]);
    AnonymousResponse::factory()->count(5)->create(['campaign_id' => $closed->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', 'always')]);

    $response = $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.dashboard', $this->tenant));

    expect(json_encode($response->viewData('page')['props']))->not->toContain('"answers"')->not->toContain('always');
});

it('reads the panel in the same number of queries however much the company holds', function (int $centers): void {
    foreach (range(1, $centers) as $ignored) {
        $center = WorkCenter::factory()->for($this->tenant)->create();
        staffOf($this->tenant, $center, 2);
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]);
        Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
        Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
    }

    $this->actingAs(ownerOf($this->tenant));
    $this->expectsDatabaseQueryCount(QUERIES_OF_THE_PANEL);

    $this->get(route('tenant.dashboard', $this->tenant))->assertOk();
})->with([1, 3]);

// Measured, then fixed so that a query per center or per evaluation breaks the test. 57: the NOM-037 declarations (A2); +1: the vacation requests that wait.
const QUERIES_OF_THE_PANEL = 61;
