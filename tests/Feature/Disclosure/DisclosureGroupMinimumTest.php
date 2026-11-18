<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Support\DisseminationPages;
use App\Modules\Disclosure\Support\Matrix;
use App\Modules\Disclosure\Support\SharePages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SharePages::class, Matrix::class, DisseminationPages::class);

// The results shared with the people are an evaluation's: they follow the minimum it froze at launch, so the page never
// promises a number its figures do not apply. What has no evaluation behind it yet (the next step) speaks of the company's now.

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    staffOf($this->tenant, $this->plant, 20);
});

/** An evaluation of the plant, closed with `$answered` questionnaires and the minimum it froze at launch. */
function evaluationClosedWithMinimum(mixed $test, int $answered, ?int $minimum, string $area = 'Producción'): Campaign
{
    [$campaign] = closedEvaluationInAreas($test->tenant, [$area => $answered]);
    $campaign->update(['work_center_id' => $test->plant->id, 'closed_at' => '2026-11-10 18:00:00', 'min_group' => $minimum]);

    return $campaign;
}

it('shares the count one at the minimum the evaluation froze, and not one under it', function (int $minimum): void {
    $under = evaluationClosedWithMinimum($this, $minimum - 1, $minimum);
    $at = evaluationClosedWithMinimum($this, $minimum, $minimum, 'Ventas');
    $this->actingAs($this->hr);

    $this->get(route('tenant.disclosure.results.share', [$this->tenant, $under->id]))
        ->assertInertia(fn (Assert $page) => $page->where('minimum', $minimum)->where('answered', null)->where('themes', []));
    $this->get(route('tenant.disclosure.results.share', [$this->tenant, $at->id]))
        ->assertInertia(fn (Assert $page) => $page->where('minimum', $minimum)->where('answered', $minimum));
})->with([3, 12]);

it('shares even one answer when the evaluation launched with no minimum', function (): void {
    $campaign = evaluationClosedWithMinimum($this, 1, 1);
    $this->actingAs($this->hr);

    $this->get(route('tenant.disclosure.results.share', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('minimum', 1)->where('answered', 1));
});

it('keeps the frozen minimum on the shared page when the company changes its own later', function (): void {
    $campaign = evaluationClosedWithMinimum($this, 4, 5);
    $this->tenant->forceFill(['min_group' => 1])->save();
    $this->actingAs($this->hr);

    $this->get(route('tenant.disclosure.results.share', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('minimum', 5)->where('answered', null));
});

it('gives each center ready to publish the minimum of its evaluation, and none to a center without one', function (): void {
    $campaign = evaluationClosedWithMinimum($this, 6, 3);
    $empty = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    $this->actingAs($this->hr);

    $this->get(route('tenant.disclosure.dissemination', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('centers.data', function ($centers) use ($campaign, $empty): bool {
            $centers = collect($centers);

            return $centers->firstWhere('id', $this->plant->id)['results'] === ['state' => 'ready', 'closedOn' => '10 nov 2026', 'campaignId' => $campaign->id, 'minimum' => 3]
                && $centers->firstWhere('id', $empty->id)['results']['minimum'] === null;
        }));
});

/**
 * Everything of 5.7 but the results is published to every center (the evaluation's factory adds one of its own) and in front
 * of its people, so the next step is to publish them.
 */
function everythingButTheResults(mixed $test): void
{
    resolve(IssuePageLink::class)->handle($test->hr->id);

    foreach ([DocumentKind::Policy, DocumentKind::ViolenceMeasures, DocumentKind::PreventionMeasures, DocumentKind::ComplaintMechanism, DocumentKind::HealthEffects] as $kind) {
        $document = Document::factory()->published()->create(['tenant_id' => $test->tenant->id, 'kind' => $kind, 'covers' => $kind === DocumentKind::Policy ? ['a', 'b', 'c'] : null]);

        foreach (WorkCenter::query()->where('tenant_id', $test->tenant->id)->pluck('id') as $center) {
            DocumentCenter::query()->create(['tenant_id' => $test->tenant->id, 'document_id' => $document->id, 'work_center_id' => $center]);
        }
    }
}

it('promises in the next step the minimum the company applies now', function (?int $minimum, string $text): void {
    $this->tenant->forceFill(['min_group' => $minimum])->save();
    evaluationClosedWithMinimum($this, 6, $minimum ?? 5);
    everythingButTheResults($this);
    $this->actingAs($this->hr);

    $this->get(route('tenant.disclosure.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('next.item', 'e')->where('next.text', $text));
})->with([
    'the platform default' => [null, 'Verán cuántas personas contestaron y los temas que más atención necesitan; nunca datos de áreas o grupos con menos de 5 personas.'],
    '12' => [12, 'Verán cuántas personas contestaron y los temas que más atención necesitan; nunca datos de áreas o grupos con menos de 12 personas.'],
    'no minimum' => [1, 'Verán cuántas personas contestaron y los temas que más atención necesitan, del centro completo y nunca por área. Tu empresa no fijó un mínimo de personas por grupo: el resultado de un grupo muy pequeño podría permitir identificar respuestas.'],
]);

it('is not moved by the minimum of another company', function (): void {
    Tenant::factory()->create(['min_group' => 1]);
    evaluationClosedWithMinimum($this, 6, null);
    everythingButTheResults($this);
    $this->actingAs($this->hr);

    $this->get(route('tenant.disclosure.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('next.text', fn (string $text): bool => str_contains($text, 'menos de 5 personas')));
});
