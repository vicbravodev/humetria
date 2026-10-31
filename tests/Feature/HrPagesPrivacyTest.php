<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\ExpectationFailedException;

// What every page HR sees receives: never one person's answers (CLAUDE.md, Privacidad).

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->evaluation = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'workers' => 30]);
    $this->active = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'workers' => 30]);
    $this->trauma = Campaign::factory()->trauma()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    // Six people answered both guides of each NOM-035 campaign; their Guía I says yes to an event, so they need care.
    foreach ([$this->evaluation, $this->active, $this->trauma] as $campaign) {
        foreach (range(1, 6) as $ignored) {
            $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->area->id]);
            $participant = Participant::factory()->create(['campaign_id' => $campaign->id, 'employee_id' => $employee->id]);

            foreach ($campaign->instruments as $instrument) {
                ParticipantInstrument::factory()->create(['participant_id' => $participant->id, 'instrument' => $instrument, 'status' => InstrumentStatus::Completed, 'completed_at' => now()]);
            }

            TraumaScreening::factory()->create(['campaign_id' => $campaign->id, 'employee_id' => $employee->id, 'answers' => guideOneAnswers([1, 7, 8, 9]), 'requires_clinical_assessment' => true]);

            if ($campaign->instruments !== ['nom035.guide-i']) {
                AnonymousResponse::factory()->create(['campaign_id' => $campaign->id, 'area_id' => $this->area->id, 'answers' => nom035Answers('nom035.guide-ii', 'almost_always')]);
            }
        }
    }
});

it('fails on answers hidden under an innocuous key, on a response id and on a yes/no Guía I', function (array $props): void {
    expect(fn () => expectNoIndividualResponses($props))->toThrow(ExpectationFailedException::class);
})->with([
    'a Guía II under "summary"' => fn (): array => ['campaign' => ['summary' => nom035Answers('nom035.guide-ii', 'never')]],
    'one answer of a list of people' => fn (): array => ['people' => ['data' => [['name' => 'Ana', 'detail' => ['12' => 'always']]]]],
    'a Guía I by item' => fn (): array => ['rows' => [guideOneAnswers([1, 7])]],
    'a gate' => fn (): array => ['row' => ['supervises' => 'yes']],
    'a map named answers' => fn (): array => ['answers' => []],
    'a response id' => fn (): array => ['link' => '/x/'.AnonymousResponse::query()->withoutGlobalScopes()->value('id')],
]);

it('lets aggregates through: counts, shares, levels and option catalogs', function (): void {
    expectNoIndividualResponses([
        'results' => ['answered' => 6, 'final' => [0, 0, 6, 0, 0], 'share' => ['always' => 0.5, 'never' => 0.5]],
        'options' => [['value' => 'always', 'label' => 'Siempre'], ['value' => 'never', 'label' => 'Nunca']],
        'status' => 'no',
        'days' => [['day' => '3 nov', 'answers' => 4]],
    ]);
});

it('gives HR no individual response on any page about campaigns, results or people', function (string $url, TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => expectNoIndividualResponses($page->toArray()['props']));
})->with([
    'dashboard' => fn (): string => route('tenant.dashboard', $this->tenant),
    'campaigns' => fn (): string => route('tenant.campaigns.index', $this->tenant),
    'a closed evaluation' => fn (): string => route('tenant.campaigns.show', [$this->tenant, $this->evaluation->id]),
    'an active evaluation and its people' => fn (): string => route('tenant.campaigns.show', [$this->tenant, $this->active->id]),
    'a Guía I campaign' => fn (): string => route('tenant.campaigns.show', [$this->tenant, $this->trauma->id]),
    'a new Guía I campaign' => fn (): string => route('tenant.campaigns.trauma.create', $this->tenant),
    'results' => fn (): string => route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id]),
    'Guía I results' => fn (): string => route('tenant.campaigns.results', [$this->tenant, $this->trauma->id]),
    'report' => fn (): string => route('tenant.campaigns.report', [$this->tenant, $this->evaluation->id]),
    'screenings' => fn (): string => route('tenant.campaigns.screenings', [$this->tenant, $this->evaluation->id]),
    'employees' => fn (): string => route('tenant.employees.index', $this->tenant),
], [TenantRole::Owner, TenantRole::Hr]);

it('gives whoever opens a shared report no individual response', function (): void {
    $token = str_repeat('a', 64);
    ReportShare::factory()->create(['campaign_id' => $this->evaluation->id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'expires_at' => now()->addHour()]);

    $this->get(route('report.shared', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => expectNoIndividualResponses($page->toArray()['props']));
});
