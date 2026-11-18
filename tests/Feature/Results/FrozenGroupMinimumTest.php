<?php

declare(strict_types=1);

use App\Modules\Assistant\Tools\GetRiskResults;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Results\Notifications\ClosingSummary;
use App\Modules\Results\Support\ReportBuilder;
use App\Modules\Results\Support\ResultsPages;
use App\Modules\Results\Support\RiskDistribution;
use App\Modules\Seating\Support\SurveyPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RiskDistribution::class, ResultsPages::class, ReportBuilder::class, ClosingSummary::class, GetRiskResults::class, SurveyPages::class);

// Every result of a campaign applies the minimum of people per group it froze when it launched, whatever the company
// asks for now: that is the number the invitation promised to whoever answered.

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
});

/** The company asks now for `$minimum` people per group. */
function companyNowAsks(Tenant $tenant, ?int $minimum): void
{
    $tenant->forceFill(['min_group' => $minimum])->save();
    app()->forgetScopedInstances();
}

/** A NOM-035 evaluation of the plant that froze `$frozen` at launch, `$status` 'active' or 'closed'. */
function evaluationFrozenAt(mixed $test, ?int $frozen, string $status = 'closed'): Campaign
{
    $factory = $status === 'closed' ? Campaign::factory()->closed() : Campaign::factory()->active('2026-11-25');

    return $factory->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->plant->id, 'min_group' => $frozen]);
}

/** `$count` people who finished the Guía II of the campaign, with their anonymous answers in `$area`. */
function guideTwoAnsweredBy(Campaign $campaign, Area $area, int $count): void
{
    foreach (range(1, $count) as $ignored) {
        ParticipantInstrument::factory()->create([
            'participant_id' => Participant::factory()->create(['campaign_id' => $campaign->id])->id,
            'instrument' => 'nom035.guide-ii',
            'status' => InstrumentStatus::Completed,
            'completed_at' => now(),
        ]);
        AnonymousResponse::factory()->create(['campaign_id' => $campaign->id, 'area_id' => $area->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', 'always')]);
    }
}

it('shows the results of a group as small as the minimum frozen at launch, though the company now asks for more', function (): void {
    $campaign = evaluationFrozenAt($this, 3);
    guideTwoAnsweredBy($campaign, $this->area, 3);
    companyNowAsks($this->tenant, 12);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.results', [$this->tenant, $campaign->id]))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $page->component('results/show')
                ->where('minGroup', 3)
                ->where('results.visible', true)
                ->where('results.answered', 3)
                ->where('areas.visible.0.answered', 3);
            expectNoIndividualResponses($page->toArray()['props']);
        });
});

it('hides the results of a group under the minimum frozen at launch, though the company now asks for less', function (): void {
    $campaign = evaluationFrozenAt($this, 12);
    guideTwoAnsweredBy($campaign, $this->area, 11);
    companyNowAsks($this->tenant, 1);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.results', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('minGroup', 12)
            ->where('results.visible', false)
            ->where('areas.visible', []));
});

it('applies the platform default to a campaign launched before campaigns kept their minimum, as it did then', function (): void {
    config(['results.min_group' => 3]);
    $campaign = evaluationFrozenAt($this, null);
    guideTwoAnsweredBy($campaign, $this->area, 3);
    companyNowAsks($this->tenant, 12);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.results', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('minGroup', 3)->where('results.visible', true));
});

it('writes in the report the minimum frozen at launch', function (): void {
    $campaign = evaluationFrozenAt($this, 12);
    companyNowAsks($this->tenant, 3);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.report', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.minGroup', 12)
            ->where('report.method', fn (string $method): bool => str_ends_with($method, 'Los resultados de grupos con menos de 12 cuestionarios no se muestran, para proteger el anonimato de las respuestas.')));
});

it('says in the report that nothing is hidden when the campaign launched with no minimum', function (): void {
    $campaign = evaluationFrozenAt($this, 1);
    companyNowAsks($this->tenant, 5);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.report', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.minGroup', 1)
            ->where('report.method', fn (string $method): bool => str_ends_with($method, 'La empresa no fijó un mínimo de cuestionarios por grupo: se muestran los resultados de todos los grupos, y el de un grupo muy pequeño podría permitir identificar respuestas.')));
});

it('tells the owner on closing with the minimum frozen at launch', function (?int $frozen, int $answered, string $says): void {
    Notification::fake();
    ownerOf($this->tenant)->update(['email' => 'olga@acme.mx']);
    $campaign = evaluationFrozenAt($this, $frozen, 'active');
    if ($answered > 0) {
        guideTwoAnsweredBy($campaign, $this->area, $answered);
    }
    companyNowAsks($this->tenant, 3);

    $this->actingAs($this->hr)->post(route('tenant.campaigns.close', [$this->tenant, $campaign->id]), ['acknowledged_shortfall' => '1'])->assertSessionHasNoErrors();

    $html = null;
    Notification::assertSentOnDemand(ClosingSummary::class, function (ClosingSummary $summary, array $channels, AnonymousNotifiable $notifiable) use (&$html): bool {
        $html = (string) $summary->toMail($notifiable)->render();

        return true;
    });
    expect($html)->toContain($says)->not->toContain('Muy alto');
})->with([
    'under the frozen 12' => [12, 11, 'Con menos de 12 cuestionarios no se muestran niveles de riesgo, para cuidar el anonimato.'],
    'no minimum and no answers' => [1, 0, 'No se recibieron cuestionarios, así que no hay niveles de riesgo que mostrar.'],
]);

it('gives the assistant the minimum frozen at launch', function (): void {
    $campaign = evaluationFrozenAt($this, 3);
    guideTwoAnsweredBy($campaign, $this->area, 3);
    companyNowAsks($this->tenant, 12);

    $card = toolCard(GetRiskResults::class, assistantScopeIn($this->tenant), ['campaign_id' => $campaign->id]);

    expect($card['minGroup'])->toBe(3)->and($card['rows'])->not->toBeEmpty();
});

it('shows the Ley Silla survey with the minimum it froze at launch', function (): void {
    Bus::fake();
    staffOf($this->tenant, $this->plant, 2);
    companyNowAsks($this->tenant, 3);
    $this->actingAs($this->hr)->post(route('tenant.seating.survey.launch', [$this->tenant, $this->plant->id]), ['closes_on' => '2026-11-25'])->assertSessionHasNoErrors();
    $survey = Campaign::query()->withoutGlobalScopes()->sole();
    AnonymousResponse::factory()->count(3)->create(['campaign_id' => $survey->id, 'area_id' => $this->area->id, 'instrument' => 'seating.survey', 'instrument_version' => 'dof-2025-07-17', 'answers' => surveyAnswers('yes')]);
    $survey->update(['status' => 'closed', 'closed_at' => now()]);
    companyNowAsks($this->tenant, 12);

    $this->actingAs($this->hr)->get(route('tenant.seating.survey', [$this->tenant, $this->plant->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('seating/survey')
            ->where('minGroup', 3)
            ->where('results.visible', true)
            ->where('results.answered', 3));
});

it('offers the company minimum of now on a center that has no Ley Silla survey yet', function (): void {
    companyNowAsks($this->tenant, 8);

    $this->actingAs($this->hr)->get(route('tenant.seating.survey', [$this->tenant, $this->plant->id]))
        ->assertInertia(fn (Assert $page) => $page->where('survey', null)->where('minGroup', 8));
});
