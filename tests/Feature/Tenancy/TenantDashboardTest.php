<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Models\ReportDetails;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Controllers\TenantDashboardController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\DashboardPage;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(TenantDashboardController::class, DashboardPage::class);

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
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')->where('hasWorkCenters', false)->where('active', null));
})->with(['none', 'only archived']);

it('stops reminding once the company has an active work center', function (): void {
    WorkCenter::factory()->for($this->tenant)->create();

    $this->actingAs(ownerOf($this->tenant))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('hasWorkCenters', true));
});

it('dates the greeting and shows the evaluation in course that closes soonest, with who answered of how many', function (): void {
    $offices = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey']);
    $sooner = Campaign::factory()->active('2026-11-10')->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $offices->id, 'instruments' => ['nom035.guide-i', 'nom035.guide-iii'], 'workers' => 60]);
    panelGuides($sooner, 'nom035.guide-iii', InstrumentStatus::Completed, 2);
    panelGuides($sooner, 'nom035.guide-iii', InstrumentStatus::Pending, 3);
    panelGuides($sooner, 'nom035.guide-i', InstrumentStatus::Pending, 5);
    Campaign::factory()->active('2026-12-01')->create(['tenant_id' => $this->tenant->id]);
    Campaign::factory()->create(['tenant_id' => $this->tenant->id]);
    Campaign::factory()->active('2026-11-01')->create(); // Another company's.

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('today', 'Viernes 30 de octubre')
            ->where('counts', ['active' => 2, 'drafts' => 1])
            ->where('active', [
                'id' => $sooner->id,
                'workCenter' => 'Oficinas Monterrey',
                'kindLabel' => 'Evaluación NOM-035',
                'instruments' => ['Guía I', 'Guía III'],
                'closesOn' => '10 nov 2026',
                'daysLeft' => 11,
                'completed' => 2,
                'target' => 5,
            ]));
});

it('measures a sampled Guía III against its sample', function (): void {
    $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'instruments' => ['nom035.guide-i', 'nom035.guide-iii'], 'population' => 'sample', 'workers' => 100, 'sample_size' => 80, 'quotas' => ['female' => 80]]);
    panelGuides($campaign, 'nom035.guide-iii', InstrumentStatus::Completed, 1);

    $this->actingAs(ownerOf($this->tenant))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('active.completed', 1)->where('active.target', 80));
});

it('lists what comes next for whoever may act on it: drafts to launch, reports to complete and people to channel', function (TenantRole $role, array $kinds): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $draft = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'workers' => 30]);
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'closed_at' => '2026-10-29 18:00:00']);
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $closed->id, 'requires_clinical_assessment' => true]);
    TraumaScreening::factory()->create(['campaign_id' => $closed->id]);
    // Reported already, or closed too long ago to be on the go: neither comes next.
    ReportDetails::factory()->create(['campaign_id' => Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id])->id]);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'closed_at' => '2026-08-01 18:00:00']);

    $steps = [
        'launch' => ['kind' => 'launch', 'campaignId' => $draft->id, 'workCenter' => 'Planta Querétaro', 'workers' => 30, 'instruments' => ['Guía I', 'Guía II'], 'people' => null],
        'report' => ['kind' => 'report', 'campaignId' => $closed->id, 'workCenter' => 'Planta Querétaro', 'workers' => null, 'instruments' => [], 'people' => null],
        'screenings' => ['kind' => 'screenings', 'campaignId' => $closed->id, 'workCenter' => 'Planta Querétaro', 'workers' => null, 'instruments' => [], 'people' => 2],
    ];

    $this->actingAs(memberOf($this->tenant, $role))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('nextSteps', array_map(fn (string $kind): array => $steps[$kind], $kinds))
            ->where('pendingSteps', count($kinds)));
})->with([
    'owner' => [TenantRole::Owner, ['launch', 'report', 'screenings']],
    'HR' => [TenantRole::Hr, ['launch', 'report', 'screenings']],
    'viewer' => [TenantRole::Viewer, []],
]);

it('shows five steps at most, counting them all', function (): void {
    Campaign::factory()->count(6)->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs(ownerOf($this->tenant))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->has('nextSteps', 5)->where('pendingSteps', 6));
});

it('pages each work center in use with where it stands in its two-year cycle', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    WorkCenter::factory()->for($this->tenant)->archived()->create();
    staffOf($this->tenant, $plant, 20);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'closed_at' => '2026-10-29 18:00:00']);

    $this->actingAs(ownerOf($this->tenant))
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cycle.total', 1)
            ->where('cycle.data.0', [
                'id' => $plant->id,
                'name' => 'Planta Querétaro',
                'workers' => 20,
                'instruments' => ['Guía I', 'Guía II'],
                'standing' => 'up_to_date',
                'from' => '29 oct 2026',
                'until' => '29 oct 2028',
                'elapsed' => 0,
            ]));
});

it('pages the closed evaluations with their participation and final score, never an answer', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'closed_at' => '2026-10-29 18:00:00']);
    panelGuides($closed, 'nom035.guide-ii', InstrumentStatus::Completed, 5);
    panelGuides($closed, 'nom035.guide-ii', InstrumentStatus::Pending, 1);
    AnonymousResponse::factory()->count(5)->create(['campaign_id' => $closed->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', 'always')]);
    $small = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id, 'closed_at' => '2026-10-01 18:00:00']);
    AnonymousResponse::factory()->count(4)->create(['campaign_id' => $small->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', 'always')]);

    $response = $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.dashboard', $this->tenant));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('levels', 5)
        ->where('closed.total', 2)
        ->where('closed.data.0.id', $closed->id)
        ->where('closed.data.0.workCenter', 'Planta Querétaro')
        ->where('closed.data.0.instruments', ['Guía I', 'Guía II'])
        ->where('closed.data.0.closedOn', '29 oct 2026')
        ->where('closed.data.0.completed', 5)
        ->where('closed.data.0.target', 6)
        ->where('closed.data.0.final', fn ($levels): bool => array_sum($levels->all()) === 5)
        // Under the minimum group, no score at all.
        ->where('closed.data.1.final', null));

    expect(json_encode($response->viewData('page')['props']))->not->toContain('"answers"')->not->toContain('always');
});

it('reads the panel in the same number of queries however much the company holds', function (int $evaluations): void {
    foreach (range(1, $evaluations) as $ignored) {
        $center = WorkCenter::factory()->for($this->tenant)->create();
        staffOf($this->tenant, $center, 2);
        Campaign::factory()->create(['tenant_id' => $this->tenant->id]);
        Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
        Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
    }

    $this->actingAs(ownerOf($this->tenant));
    $this->expectsDatabaseQueryCount(22 + min($evaluations, 5));

    $this->get(route('tenant.dashboard', $this->tenant))->assertOk();
})->with([1, 3]);
