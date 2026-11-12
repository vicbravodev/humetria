<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Interventions\Models\Program;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Models\ReportDetails;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\Nom035Rows;
use App\Modules\Tenancy\Support\RoutePage;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RoutePage::class, Nom035Rows::class);

// Rediseño v2, board V2-Ruta-N035: the NOM-035 route of one center after evaluating, its six stages each with what the norm
// asks for in plain words and its way to do it, who waits to be channeled first (only for whoever may see the Guía I), and
// how ready the center is for an inspection (V3-Inspeccion).

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-04 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficina']);
    staffOf($this->tenant, $this->center, 30);
    $this->closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'closed_at' => '2026-11-02 18:00:00']);
});

function nom035Route(?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.nom035.route', [test()->tenant, ($center ?? test()->center)->id]));
}

it('shows the six stages of the center, each with what it asks in plain words and its way to do it', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    nom035Route()->assertInertia(fn (Assert $page) => $page->component('tenancy/nom035-route')
        ->where('workCenter', ['id' => $this->center->id, 'name' => 'Oficina'])
        ->where('people', 30)
        ->where('evaluation', ['closedOn' => '2 nov 2026', 'answered' => 0, 'target' => 0])
        ->where('route.done', [false, true, true, false, true, false])
        ->has('rows', 6)
        ->where('rows', fn ($rows): bool => collect($rows)->pluck('stage')->all() === [1, 2, 3, 4, 5, 6])
        ->where('rows.0', [
            'numeral' => '5.1',
            'title' => 'Política de prevención por escrito',
            'detail' => 'Aún no hay una política vigente en este centro',
            'state' => 'missing',
            'stage' => 1,
            'href' => route('tenant.disclosure.write', [$this->tenant, 'policy', 'center' => $this->center->id]),
            'action' => 'Escribir la política',
        ])
        ->where('rows.1', [
            'numeral' => '7.1 y 7.9',
            'title' => 'Evaluación de los factores de riesgo, vigente',
            'detail' => 'Cerrada el 2 nov 2026: contestaron 0 de 0',
            'state' => 'done',
            'stage' => 2,
            'href' => route('tenant.campaigns.results', [$this->tenant, $this->closed->id]),
            'action' => 'Ver resultados',
        ])
        ->where('rows.3.state', 'missing')
        ->where('rows.3.detail', 'Aún no compartes los resultados de la última evaluación')
        ->where('rows.3.href', route('tenant.disclosure.results.share', [$this->tenant, $this->closed->id]))
        ->where('rows.3.action', 'Preparar el resumen')
        // Closed with nothing in Medio or more: no Programa is needed (num. 8.3).
        ->where('rows.4.state', 'not_applicable')
        ->where('rows.5', [
            'numeral' => '7.7',
            'title' => 'Informe de resultados',
            'detail' => 'Le faltan conclusiones, recomendaciones y responsable',
            'state' => 'missing',
            'stage' => 6,
            'href' => route('tenant.campaigns.report', [$this->tenant, $this->closed->id]),
            'action' => 'Completar el informe',
        ])
        ->where('urgencies', [])
        ->where('inspectionHref', route('tenant.nom035.inspection', [$this->tenant, $this->center->id])));
});

it('says with how many answers the evaluation closed, in the route and in the inspection', function (int $answered, int $invited): void {
    foreach (range(1, $invited) as $index) {
        ParticipantInstrument::factory()->create([
            'participant_id' => Participant::factory()->create(['campaign_id' => $this->closed->id])->id,
            'instrument' => 'nom035.guide-ii',
            ...($index <= $answered ? ['status' => InstrumentStatus::Completed, 'completed_at' => now()] : []),
        ]);
    }

    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $detail = "Cerrada el 2 nov 2026: contestaron {$answered} de {$invited}";

    nom035Route()->assertInertia(fn (Assert $page) => $page
        ->where('rows.1.state', 'done')
        ->where('rows.1.detail', $detail));
    $this->get(route('tenant.nom035.inspection', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page->where('ready', fn ($rows): bool => collect($rows)->firstWhere('numeral', '7.1 y 7.9')['detail'] === $detail));
})->with([
    'half of them' => [10, 20],
    'everyone' => [20, 20],
]);

it('puts first the people who wait to be channeled, only for whoever may see the Guía I', function (TenantRole $role, string $state, string $detail, int $urgencies): void {
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $this->closed->id, 'requires_clinical_assessment' => true]);
    $this->actingAs(memberOf($this->tenant, $role));

    nom035Route()->assertInertia(fn (Assert $page) => $page
        ->where('rows.2.state', $state)
        ->where('rows.2.detail', $detail)
        ->has('urgencies', $urgencies));
})->with([
    'HR' => [TenantRole::Hr, 'missing', '2 personas esperan valoración clínica', 1],
    // C-28: the viewer does not see the list, not even counted; the stage reads as done, as in the panel, and says why.
    'a viewer' => [TenantRole::Viewer, 'done', 'Solo lo ve quien puede ver la Guía I', 0],
]);

it('says how many people wait and opens the list where they are channeled', function (): void {
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $this->closed->id, 'requires_clinical_assessment' => true]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));

    nom035Route()->assertInertia(fn (Assert $page) => $page->where('rows.2', [
        'numeral' => '5.5 y 5.8 c',
        'title' => 'Quien lo requiere según la Guía I, canalizado',
        'detail' => '2 personas esperan valoración clínica',
        'state' => 'missing',
        'stage' => 3,
        'href' => route('tenant.campaigns.screenings', [$this->tenant, $this->closed->id]),
        'action' => 'Canalizar',
    ]));
});

it('reads the Programa where it stands, and the stages met with the way to see them', function (): void {
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $this->closed->id]);
    PageLink::factory()->create(['tenant_id' => $this->tenant->id]);
    ReportDetails::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $this->closed->id]);
    Program::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $this->closed->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    nom035Route()->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.state', 'done')
        ->where('rows.0.action', 'Ver')
        ->where('rows.0.href', route('tenant.disclosure.policy', $this->tenant))
        ->where('rows.3.state', 'done')
        ->where('rows.4', [
            'numeral' => '8.3 a 8.5',
            'title' => 'Programa de intervención con acciones, responsables y fechas',
            'detail' => 'Aún no tiene acciones',
            'state' => 'missing',
            'stage' => 5,
            'href' => route('tenant.campaigns.program', [$this->tenant, $this->closed->id]),
            'action' => 'Armar el programa',
        ])
        ->where('rows.5.state', 'done'));
});

it('does not read 7.8 as done while the published results are not in front of the people (C-52)', function (): void {
    ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $this->closed->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    nom035Route()->assertInertia(fn (Assert $page) => $page->where('rows.3.state', 'missing'));

    PageLink::factory()->create(['tenant_id' => $this->tenant->id]);
    nom035Route()->assertInertia(fn (Assert $page) => $page->where('rows.3.state', 'done'));
});

it('waits for the evaluation before what follows it, and asks a small center only for the Guía I', function (): void {
    $small = WorkCenter::factory()->for($this->tenant)->create();
    staffOf($this->tenant, $small, 15);
    $new = WorkCenter::factory()->for($this->tenant)->create();
    staffOf($this->tenant, $new, 20);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    nom035Route($new)->assertInertia(fn (Assert $page) => $page
        ->where('evaluation', null)
        ->where('rows.1.state', 'missing')
        ->where('rows.1.detail', 'El centro no se ha evaluado')
        ->where('rows.1.href', route('tenant.campaigns.index', $this->tenant))
        ->where('rows.1.action', 'Evaluar')
        ->where('rows', fn ($rows): bool => collect($rows)->slice(2)->every(fn (array $row): bool => $row['state'] === 'missing' && $row['detail'] === 'Llega al cerrar la evaluación' && $row['href'] === null)));
    // Num. 2 a: up to 15 people, the Guía I only; there is nothing to share, no Programa and no report to write.
    nom035Route($small)->assertInertia(fn (Assert $page) => $page
        ->where('rows', fn ($rows): bool => collect($rows)->slice(3)->every(fn (array $row): bool => $row['state'] === 'not_applicable' && $row['detail'] === 'No aplica: hasta 15 personas solo contestan la Guía I')));
});

it('shows the viewer the stages without a way into them', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    nom035Route()->assertInertia(fn (Assert $page) => $page
        ->where('rows', fn ($rows): bool => collect($rows)->every(fn (array $row): bool => $row['href'] === null && $row['action'] === null)));
});

it('reads the page in a fixed number of queries', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    nom035Route()->assertOk();

    $this->expectsDatabaseQueryCount(25);
    nom035Route()->assertOk();
});

// Board V3-Inspeccion for NOM-035: what the center can show and what is missing, from the same requirements as its route.
it('splits what an inspector would ask for into what the center can show and what is missing', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.nom035.inspection', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/nom035-inspection')
            ->where('workCenter', ['id' => $this->center->id, 'name' => 'Oficina'])
            // The Programa does not apply: nothing reached Medio.
            ->where('applicable', 5)
            ->where('ready', fn ($rows): bool => collect($rows)->pluck('numeral')->all() === ['7.1 y 7.9', '5.5 y 5.8 c'])
            ->where('missing', fn ($rows): bool => collect($rows)->pluck('numeral')->all() === ['5.1', '7.8', '7.7'])
            ->missing('missing.0.stage')
            ->where('dossierHref', route('tenant.disclosure.dossier', [$this->tenant, $this->center->id])));
});

it('gives the viewer the inspection without the dossier, and answers 404 for a center of another company', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    $this->get(route('tenant.nom035.inspection', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page->where('dossierHref', null));
    $this->get(route('tenant.nom035.inspection', [$this->tenant, WorkCenter::factory()->create()->id]))->assertNotFound();
    $this->get(route('tenant.nom035.inspection', [$this->tenant, WorkCenter::factory()->for($this->tenant)->archived()->create()->id]))->assertNotFound();
});

it('opens the NOM-035 route from the route of a center, the panel\'s way in', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.work-centers.route', [$this->tenant, $this->center->id, 'nom035']))
        ->assertRedirect(route('tenant.nom035.route', [$this->tenant, $this->center->id]));
    nom035Route(WorkCenter::factory()->create())->assertNotFound();
    $this->get("/admin/acme/campaigns/centers/{$this->center->id}x/route")->assertNotFound();
});
