<?php

declare(strict_types=1);

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Actions\PublishResults;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    workIn($this->tenant);
});

/** The board's row `$code` of the plant, or null. @return array<string, mixed>|null */
function resultsRow(object $test, string $code): ?array
{
    $row = null;
    $test->actingAs($test->hr)->get(route('tenant.disclosure.index', $test->tenant))->assertInertia(function (Assert $page) use (&$row, $code): void {
        $row = collect(collect($page->toArray()['props']['centers']['data'])->firstWhere('name', 'Planta Norte')['requirements'])->firstWhere('code', $code);
    });

    return $row;
}

function evaluationOfPlant(object $test, string $value = 'always', int $per = 6, string $closedAt = '2026-11-10 18:00:00'): Campaign
{
    static $number = 0;
    [$campaign] = closedEvaluationInAreas($test->tenant, ['Área '.++$number => $per], $value);
    $campaign->update(['work_center_id' => $test->plant->id, 'closed_at' => $closedAt]);

    return $campaign;
}

function policyPublishedOn(object $test, string $at): void
{
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => $test->tenant->id, 'published_by' => $test->hr->id, 'published_at' => $at]);
    DocumentCenter::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $test->plant->id]);
}

it('does not apply to a center of up to 15 people, and says why', function (): void {
    staffOf($this->tenant, $this->plant, 15);

    expect(resultsRow($this, 'D7'))->toMatchArray(['numeral' => '5.7 e, 7.8', 'detail' => 'El numeral 7.8 no aplica a centros de hasta 15 personas (numeral 2 a).'])
        ->and(resultsRow($this, 'D7')['state']['value'])->toBe('not_applicable');
});

it('is missing in a center of 16 with no closed evaluation yet', function (): void {
    staffOf($this->tenant, $this->plant, 16);

    expect(resultsRow($this, 'D7')['state']['value'])->toBe('missing')->and(resultsRow($this, 'D7')['detail'])->toBe('Aún no hay una evaluación cerrada.');
});

it('is missing while the closed evaluation is not published to the people', function (): void {
    staffOf($this->tenant, $this->plant, 20);
    evaluationOfPlant($this);

    expect(resultsRow($this, 'D7')['state']['value'])->toBe('missing')->and(resultsRow($this, 'D7')['detail'])->toBe('La evaluación cerrada el 10 nov 2026 no se ha publicado a las personas.');
});

it('is met once the latest closed evaluation is published and can be shown', function (): void {
    staffOf($this->tenant, $this->plant, 20);
    resolve(PublishResults::class)->handle(evaluationOfPlant($this)->id, $this->hr->id);

    expect(resultsRow($this, 'D7'))->toMatchArray(['detail' => null])->and(resultsRow($this, 'D7')['state']['value'])->toBe('met');
});

it('warns when it is published but with too few questionnaires to show figures', function (): void {
    staffOf($this->tenant, $this->plant, 20);
    resolve(PublishResults::class)->handle(evaluationOfPlant($this, per: 4)->id, $this->hr->id);

    expect(resultsRow($this, 'D7')['state']['value'])->toBe('notice')->and(resultsRow($this, 'D7')['detail'])->toBe('Publicada, pero con muy pocas respuestas: las cifras no se muestran para proteger la confidencialidad.');
});

it('warns when a closed evaluation more recent than the published one is not published', function (): void {
    staffOf($this->tenant, $this->plant, 20);
    resolve(PublishResults::class)->handle(evaluationOfPlant($this, closedAt: '2026-06-01 18:00:00')->id, $this->hr->id);
    evaluationOfPlant($this, closedAt: '2026-11-10 18:00:00');

    expect(resultsRow($this, 'D7')['state']['value'])->toBe('notice')->and(resultsRow($this, 'D7')['detail'])->toBe('Hay una evaluación cerrada el 10 nov 2026, más reciente que la publicada, sin publicar.');
});

it('does not apply when the evaluation applied only the Guía I', function (): void {
    staffOf($this->tenant, $this->plant, 20);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'instruments' => ['nom035.guide-i']]);

    expect(resultsRow($this, 'D7')['state']['value'])->toBe('not_applicable');
});

it('says to review the policy when the evaluation has medium risk or more and the policy in force is older than its closing', function (): void {
    staffOf($this->tenant, $this->plant, 20);
    evaluationOfPlant($this, 'always');
    policyPublishedOn($this, '2026-11-01 18:00:00');

    expect(resultsRow($this, 'D9')['numeral'])->toBe('Tablas 4 y 7')
        ->and(resultsRow($this, 'D9')['state']['value'])->toBe('notice')
        ->and(resultsRow($this, 'D9')['detail'])->toBe('La evaluación cerrada el 10 nov 2026 tiene riesgo medio o más y la política vigente es anterior: revise la política y refuerce su aplicación y difusión.');
});

it('asks for more disclosure when the risk is low and the policy is older', function (): void {
    $this->app->bind(RiskResults::class, fn () => new class implements RiskResults
    {
        public function forCampaign(CampaignRecord $campaign, ?string $areaId = null): RiskBreakdown
        {
            return new RiskBreakdown(RiskBreakdown::AVAILABLE, 6, [0, 6, 0, 0, 0]);
        }

        public function areaGroups(CampaignRecord $campaign): array
        {
            return [];
        }
    });
    staffOf($this->tenant, $this->plant, 20);
    evaluationOfPlant($this, 'always');
    policyPublishedOn($this, '2026-11-01 18:00:00');

    expect(resultsRow($this, 'D9')['detail'])->toBe('La evaluación cerrada el 10 nov 2026 tiene riesgo bajo y la política vigente es anterior: dé mayor difusión a la política.');
});

it('says nothing when the policy is as recent as the closing or newer, when there is no policy, or when the risk is nil', function (Closure $arrange): void {
    staffOf($this->tenant, $this->plant, 20);
    $arrange($this);

    expect(resultsRow($this, 'D9'))->toBeNull();
})->with([
    'a policy published after the closing' => [function ($test): void {
        evaluationOfPlant($test, 'always');
        policyPublishedOn($test, '2026-11-12 18:00:00');
    }],
    'a policy published the day it closed' => [function ($test): void {
        evaluationOfPlant($test, 'always');
        policyPublishedOn($test, '2026-11-10 20:00:00');
    }],
    'no policy' => [fn ($test) => evaluationOfPlant($test, 'always')],
    'no evaluation' => [fn ($test) => policyPublishedOn($test, '2026-11-01 18:00:00')],
    'too few questionnaires to know' => [function ($test): void {
        evaluationOfPlant($test, 'always', 4);
        policyPublishedOn($test, '2026-11-01 18:00:00');
    }],
]);
