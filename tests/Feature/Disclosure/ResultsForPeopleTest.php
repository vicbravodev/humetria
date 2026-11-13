<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\PublishResults;
use App\Modules\Disclosure\Actions\WithdrawResults;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\AreaRisk;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PublishResults::class, WithdrawResults::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    staffOf($this->tenant, $this->plant, 20);
    workIn($this->tenant);
});

/** A closed evaluation of the plant answered by `$sizes` questionnaires per area. @return Campaign */
function disclosureResultsEvaluation(object $test, array $sizes = ['Producción' => 6, 'Ventas' => 6], ?string $closedAt = null): Campaign
{
    static $number = 0;
    $number++;
    // An area is named once per company: later evaluations of the same test get names of their own.
    $sizes = $number === 1 ? $sizes : array_combine(array_map(fn (string $name): string => "{$name} {$number}", array_keys($sizes)), array_values($sizes));
    [$campaign] = closedEvaluationInAreas($test->tenant, $sizes);
    $campaign->update(['work_center_id' => $test->plant->id, ...($closedAt === null ? [] : ['closed_at' => $closedAt])]);

    return $campaign;
}

it('publishes a closed evaluation to the people, once', function (): void {
    Log::spy();
    $campaign = disclosureResultsEvaluation($this);

    $publication = resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);
    $again = resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);

    expect($again->id)->toBe($publication->id)
        ->and(ResultPublication::query()->count())->toBe(1)
        ->and($publication->campaign_id)->toBe($campaign->id)
        ->and($publication->work_center_id)->toBe($this->plant->id)
        ->and($publication->published_by)->toBe($this->hr->id)
        ->and($publication->withdrawn_at)->toBeNull();
    expect(AuditEntry::query()->where('event', 'disclosure.results_published')->count())->toBe(1)
        ->and(AuditEntry::query()->where('event', 'disclosure.results_published')->sole()->metadata)->toEqual(['campaign_id' => $campaign->id, 'work_center_id' => $this->plant->id, 'show_measures' => false]);
    Log::shouldHaveReceived('info')->with('disclosure.results_published', ['campaign_id' => $campaign->id, 'work_center_id' => $this->plant->id, 'show_measures' => false])->once();
});

it('publishes also when there are too few questionnaires to show figures: the page says so without a count', function (): void {
    $campaign = disclosureResultsEvaluation($this, ['Producción' => 4]);

    expect(resolve(PublishResults::class)->handle($campaign->id, $this->hr->id)->campaign_id)->toBe($campaign->id);
});

it('refuses what cannot be shown: an evaluation still open or of only the Guía I', function (Closure $campaign): void {
    expect(fn () => resolve(PublishResults::class)->handle($campaign($this), $this->hr->id))->toThrow(ValidationException::class);
    expect(ResultPublication::query()->count())->toBe(0);
})->with([
    'open' => [fn ($test) => Campaign::factory()->active()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->plant->id])->id],
    'only the Guía I' => [fn ($test) => Campaign::factory()->trauma()->closed()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->plant->id])->id],
]);

it('does not find what is another company\'s, does not exist or is not an id', function (Closure $campaign): void {
    expect(fn () => resolve(PublishResults::class)->handle($campaign($this), $this->hr->id))->toThrow(ModelNotFoundException::class);
})->with([
    'of another company' => [fn ($test) => Campaign::factory()->closed()->create()->id],
    'that does not exist' => [fn ($test) => '0194c1de-0000-7000-8000-000000000000'],
    'not an id' => [fn ($test) => 'x'],
]);

it('withdraws a publication, keeps it, and lets it be published again as a new row', function (): void {
    $campaign = disclosureResultsEvaluation($this);
    $first = resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);

    $withdrawn = resolve(WithdrawResults::class)->handle($campaign->id, $this->hr->id);

    expect($withdrawn->id)->toBe($first->id)->and($withdrawn->withdrawn_at)->not->toBeNull()->and($withdrawn->withdrawn_by)->toBe($this->hr->id)
        ->and(AuditEntry::query()->where('event', 'disclosure.results_withdrawn')->count())->toBe(1);

    $second = resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);

    expect($second->id)->not->toBe($first->id)->and(ResultPublication::query()->count())->toBe(2)
        ->and(ResultPublication::query()->whereNull('withdrawn_at')->count())->toBe(1);
});

it('has nothing to withdraw when nothing is published', function (): void {
    expect(fn () => resolve(WithdrawResults::class)->handle(disclosureResultsEvaluation($this)->id, $this->hr->id))->toThrow(ModelNotFoundException::class);
});

it('keeps one active publication per evaluation and no column that points at a person', function (): void {
    $campaign = disclosureResultsEvaluation($this);
    resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);

    expect(fn () => DB::transaction(fn () => ResultPublication::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $campaign->id, 'work_center_id' => $this->plant->id])))->toThrow(QueryException::class, 'disclosure_result_publications_one_active');

    $columns = Schema::getColumnListing('disclosure_result_publications');
    expect($columns)->toEqualCanonicalizing(['id', 'tenant_id', 'campaign_id', 'work_center_id', 'show_measures', 'published_at', 'published_by', 'withdrawn_at', 'withdrawn_by', 'created_at', 'updated_at']);
    $foreign = collect(Schema::getForeignKeys('disclosure_result_publications'))->pluck('foreign_table')->unique()->sort()->values()->all();
    expect($foreign)->toBe(['campaigns', 'tenants', 'users', 'work_centers']);
});

/** The results block of the public page of the plant. @return array<string, mixed>|null */
function disclosurePlantResults(object $test): ?array
{
    $token = (string) resolve(IssuePageLink::class)->handle($test->hr->id)->token();
    $props = $test->get(route('workers.center', [$token, $test->plant->id]))->viewData('page')['props'];

    return $props['results'];
}

it('shows the people the published results of the plant, centered on the whole center, with the counts of each level', function (): void {
    $campaign = disclosureResultsEvaluation($this, ['Producción' => 7, 'Ventas' => 6], '2026-11-10 18:00:00');
    resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);

    $results = disclosurePlantResults($this);

    expect($results['published'])->toMatchArray(['state' => 'available', 'closedOn' => '10 nov 2026', 'answered' => 13, 'target' => 13, 'guide' => 'ii'])
        ->and(array_sum($results['published']['final']))->toBe(13)
        ->and($results['published']['final'])->toHaveCount(5)
        ->and($results['title'])->toBe('Resultados de la identificación y análisis de los factores de riesgo psicosocial')
        ->and($results['published']['categories'])->not->toBeEmpty()
        ->and(json_encode($results))->not->toContain('Producción')->not->toContain('Ventas')->not->toContain('areas')->not->toContain('hiddenAreas');
});

it('shows nothing before RH publishes, after it withdraws, and nothing of an evaluation still open', function (): void {
    $campaign = disclosureResultsEvaluation($this);

    expect(disclosurePlantResults($this)['published'])->toBeNull();

    resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);
    expect(disclosurePlantResults($this)['published'])->not->toBeNull();

    resolve(WithdrawResults::class)->handle($campaign->id, $this->hr->id);
    expect(disclosurePlantResults($this)['published'])->toBeNull();
});

it('says the figures are not shown when too few answered, with no count, and shows them at the minimum', function (int $answered, string $state): void {
    $campaign = disclosureResultsEvaluation($this, ['Producción' => $answered]);
    resolve(PublishResults::class)->handle($campaign->id, $this->hr->id);

    $published = disclosurePlantResults($this)['published'];

    expect($published['state'])->toBe($state);
    if ($state === 'below_minimum') {
        expect($published)->toBe(['state' => 'below_minimum', 'closedOn' => $published['closedOn']])->not->toHaveKey('answered');
    }
})->with([
    '4 questionnaires' => [4, 'below_minimum'],
    '5 questionnaires' => [5, 'available'],
]);

it('keeps showing the published evaluation when a newer one closes without being published', function (): void {
    $older = disclosureResultsEvaluation($this, ['Producción' => 6], '2026-06-01 18:00:00');
    resolve(PublishResults::class)->handle($older->id, $this->hr->id);
    disclosureResultsEvaluation($this, ['Producción' => 8], '2026-11-01 18:00:00');

    expect(disclosurePlantResults($this)['published']['closedOn'])->toBe('1 jun 2026');
});

it('shows the most recent of several published evaluations of the plant', function (): void {
    foreach (['2026-03-01 18:00:00', '2026-11-01 18:00:00', '2026-06-01 18:00:00'] as $day) {
        resolve(PublishResults::class)->handle(disclosureResultsEvaluation($this, ['Producción' => 6], $day)->id, $this->hr->id);
    }

    expect(disclosurePlantResults($this)['published']['closedOn'])->toBe('1 nov 2026');
});

it('only ever asks Results for the whole center, never for an area', function (): void {
    $calls = new ArrayObject;
    $this->app->extend(RiskResults::class, fn (RiskResults $real) => new class($real, $calls) implements RiskResults
    {
        public function __construct(private RiskResults $real, private ArrayObject $calls) {}

        public function forCampaign(CampaignRecord $campaign, ?string $areaId = null): RiskBreakdown
        {
            $this->calls[] = $areaId;

            return $this->real->forCampaign($campaign, $areaId);
        }

        /** @return list<AreaRisk> */
        public function areaGroups(CampaignRecord $campaign): array
        {
            $this->calls[] = 'areaGroups';

            return $this->real->areaGroups($campaign);
        }
    });
    resolve(PublishResults::class)->handle(disclosureResultsEvaluation($this)->id, $this->hr->id);
    disclosurePlantResults($this);

    expect($calls->getArrayCopy())->not->toBeEmpty()->and(array_values(array_unique($calls->getArrayCopy(), SORT_REGULAR)))->toBe([null]);
});

it('gives no results block to a center of up to 15 people, and one to a center of 16', function (int $people, bool $block): void {
    $small = WorkCenter::factory()->for($this->tenant)->create();
    staffOf($this->tenant, $small, $people);
    $token = (string) resolve(IssuePageLink::class)->handle($this->hr->id)->token();

    $this->get(route('workers.center', [$token, $small->id]))->assertInertia(fn (Assert $page) => $block ? $page->where('results.published', null) : $page->where('results', null));
})->with([
    '15 people' => [15, false],
    '16 people' => [16, true],
]);

it('titles the results by the guide: the work environment is evaluated only above 50 people', function (): void {
    $big = WorkCenter::factory()->for($this->tenant)->create();
    staffOf($this->tenant, $big, 51);
    $token = (string) resolve(IssuePageLink::class)->handle($this->hr->id)->token();

    $this->get(route('workers.center', [$token, $big->id]))->assertInertia(fn (Assert $page) => $page->where('results.title', 'Resultados de la identificación y análisis de los factores de riesgo psicosocial y de la evaluación del entorno organizacional'));
});

it('says whether the page of the people is active, so publishing warns they have nowhere to read the results yet', function (): void {
    disclosureResultsEvaluation($this);
    $page = fn () => $this->actingAs($this->hr)->get(route('tenant.disclosure.results', $this->tenant));

    $page()->assertInertia(fn (Assert $page) => $page->where('pageActive', false));

    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $page()->assertInertia(fn (Assert $page) => $page->where('pageActive', true));

    $link->update(['disabled_at' => now()]);
    $page()->assertInertia(fn (Assert $page) => $page->where('pageActive', false));
});

it('asks the evaluations closed, a page at a time, and lets only whoever manages publish and withdraw', function (): void {
    $campaign = disclosureResultsEvaluation($this);
    $closed = fn () => $this->actingAs($this->hr)->get(route('tenant.disclosure.results', $this->tenant));

    $closed()->assertOk()->assertInertia(fn (Assert $page) => $page->component('disclosure/results')
        ->where('evaluations.total', 1)
        ->where('evaluations.data.0', fn ($row) => $row['id'] === $campaign->id && $row['workCenter'] === 'Planta Norte' && $row['closedOn'] !== null && $row['state'] === 'unpublished' && $row['canPublish'] === true));

    $this->actingAs($this->hr)->post(route('tenant.disclosure.results.publish', [$this->tenant, $campaign->id]))->assertRedirect(route('tenant.disclosure.results', $this->tenant));
    $closed()->assertInertia(fn (Assert $page) => $page->where('evaluations.data.0.state', 'published')->where('evaluations.data.0.publishedOn', '20 nov 2026'));

    $this->actingAs($this->hr)->post(route('tenant.disclosure.results.withdraw', [$this->tenant, $campaign->id]))->assertRedirect(route('tenant.disclosure.results', $this->tenant));
    $closed()->assertInertia(fn (Assert $page) => $page->where('evaluations.data.0.state', 'unpublished'));

    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->actingAs($viewer)->get(route('tenant.disclosure.results', $this->tenant))->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.disclosure.results.publish', [$this->tenant, $campaign->id]))->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.disclosure.results.withdraw', [$this->tenant, $campaign->id]))->assertForbidden();
});

it('does not publish the evaluation of another company: the same 404 as one that does not exist', function (): void {
    $foreign = Campaign::factory()->closed()->create();

    $this->actingAs($this->hr)->post(route('tenant.disclosure.results.publish', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->actingAs($this->hr)->post(route('tenant.disclosure.results.publish', [$this->tenant, '0194c1de-0000-7000-8000-000000000000']))->assertNotFound();
    $this->actingAs($this->hr)->post(route('tenant.disclosure.results.withdraw', [$this->tenant, $foreign->id]))->assertNotFound();
});

it('marks an evaluation of only the Guía I as one the people do not see', function (): void {
    $trauma = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'instruments' => ['nom035.guide-i']]);

    $this->actingAs($this->hr)->get(route('tenant.disclosure.results', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('evaluations.data', fn ($rows) => collect($rows)->firstWhere('id', $trauma->id)['canPublish'] === false));
    $this->actingAs($this->hr)->post(route('tenant.disclosure.results.publish', [$this->tenant, $trauma->id]))->assertSessionHasErrors('campaign');
});

it('scopes the publications to the current company', function (): void {
    $other = Tenant::factory()->create();
    ResultPublication::factory()->create(['tenant_id' => $other->id]);
    $own = resolve(PublishResults::class)->handle(disclosureResultsEvaluation($this)->id, $this->hr->id);

    expect(ResultPublication::query()->pluck('id')->all())->toBe([$own->id])
        ->and(app(CampaignRecords::class)->find($own->campaign_id))->not->toBeNull();
});
