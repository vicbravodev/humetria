<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Http\Controllers\ResultsController;
use App\Modules\Results\Support\ResultsPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ResultsController::class, ResultsPages::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $this->plantArea = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $this->evaluation = Campaign::factory()->closed()->create([
        'tenant_id' => $this->tenant->id,
        'work_center_id' => $this->plant->id,
        'workers' => 30,
        'launched_at' => '2026-11-02 16:00:00',
        'closed_at' => '2026-11-19 18:00:00',
    ]);
});

/** `$count` people of the campaign who answered `$instrument` (and, for Guía II, their anonymous answers in `$area`). */
function answeredBy(Campaign $campaign, string $instrument, int $count, ?Area $area = null, string $value = 'never'): void
{
    foreach (range(1, $count) as $ignored) {
        ParticipantInstrument::factory()->create([
            'participant_id' => Participant::factory()->create(['campaign_id' => $campaign->id])->id,
            'instrument' => $instrument,
            'status' => InstrumentStatus::Completed,
            'completed_at' => now(),
        ]);

        if ($area !== null) {
            AnonymousResponse::factory()->create(['campaign_id' => $campaign->id, 'area_id' => $area->id, 'instrument' => $instrument, 'answers' => nom035Answers($instrument, $value)]);
        }
    }
}

/** Whether `$key` appears as a key anywhere in `$value`. */
function propsHaveKey(mixed $value, string $key): bool
{
    if (! is_array($value)) {
        return false;
    }

    foreach ($value as $name => $item) {
        if ($name === $key || propsHaveKey($item, $key)) {
            return true;
        }
    }

    return false;
}

it('shows a closed evaluation by risk level: the center, its categories and domains, its areas and its questions', function (): void {
    answeredBy($this->evaluation, 'nom035.guide-ii', 6, $this->plantArea, 'never');
    answeredBy($this->evaluation, 'nom035.guide-ii', 5, $this->sales, 'always');
    ParticipantInstrument::factory()->create(['participant_id' => Participant::factory()->create(['campaign_id' => $this->evaluation->id])->id, 'instrument' => 'nom035.guide-ii']);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('results/show')
            ->where('campaign', [
                'id' => $this->evaluation->id,
                'workCenter' => 'Planta Norte',
                'kindLabel' => 'Evaluación NOM-035',
                'closed' => true,
                'launchedOn' => '2 nov 2026',
                'closedOn' => '19 nov 2026',
                'workers' => 30,
                'sampleSize' => null,
            ])
            ->where('guide', ['key' => 'nom035.guide-ii', 'title' => 'Guía II'])
            ->where('participation', ['invited' => 12, 'answered' => 11])
            ->where('results.answered', 11)
            ->where('results.final', [0, 0, 6, 0, 5])
            ->has('results.categories', 4)
            ->has('results.domains', 8)
            ->where('areas', [
                'visible' => [
                    ['id' => $this->plantArea->id, 'name' => 'Producción', 'answered' => 6, 'final' => [0, 0, 6, 0, 0]],
                    ['id' => $this->sales->id, 'name' => 'Ventas', 'answered' => 5, 'final' => [0, 0, 0, 0, 5]],
                ],
                'hidden' => 0,
            ])
            ->where('items.0.items.0.counts', [5, 0, 0, 0, 6])
            ->where('items.0.items.0.unfavorable', 5)
            ->where('filters.area', null)
            ->where('levels.0', ['value' => 'null', 'label' => 'Nulo o despreciable', 'action' => 'El riesgo resulta despreciable por lo que no se requiere medidas adicionales.'])
            ->where('levels.4.label', 'Muy alto')
            ->where('trauma', ['answered' => 0, 'requiring' => 0]));
});

it('filters the center by an area it shows', function (): void {
    answeredBy($this->evaluation, 'nom035.guide-ii', 6, $this->plantArea, 'never');
    answeredBy($this->evaluation, 'nom035.guide-ii', 5, $this->sales, 'always');

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id, 'area' => $this->sales->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.answered', 5)
            ->where('results.final', [0, 0, 0, 0, 5])
            ->where('filters.area', ['value' => $this->sales->id, 'label' => 'Ventas'])
            // Questions are only shown for the whole center: per area, the center minus the area would reveal the rest.
            ->where('items.0.items.0.counts', [5, 0, 0, 0, 6]));
});

it('ignores an area filter it does not show', function (string $area): void {
    answeredBy($this->evaluation, 'nom035.guide-ii', 10, $this->plantArea);
    answeredBy($this->evaluation, 'nom035.guide-ii', 2, $this->sales);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id, 'area' => $area === 'sales' ? $this->sales->id : $area]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.answered', 12)
            ->where('areas', ['visible' => [], 'hidden' => 2])
            ->where('filters.area', null));
})->with(['sales', 'not-an-id', '00000000-0000-4000-8000-000000000000']);

it('shows nothing of a center with fewer than 5 questionnaires', function (): void {
    answeredBy($this->evaluation, 'nom035.guide-ii', 4, $this->plantArea);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('results', ['answered' => 4, 'visible' => false, 'final' => [], 'categories' => [], 'domains' => []])
            ->where('areas', ['visible' => [], 'hidden' => 1])
            ->where('items.0.items.0.counts', null));
});

it('shows no Guía II or III result while the evaluation is open: its movement would tell who answered what', function (): void {
    $open = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]);
    answeredBy($open, 'nom035.guide-ii', 6, $this->plantArea);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.results', [$this->tenant, $open->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('campaign.closed', false)
            ->where('guide', ['key' => 'nom035.guide-ii', 'title' => 'Guía II'])
            ->where('participation', ['invited' => 6, 'answered' => 6])
            ->where('results', null)
            ->where('areas', null)
            ->where('items', null));
});

it('shows a Guía I campaign with its screenings only', function (): void {
    $trauma = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $trauma->id, 'requires_clinical_assessment' => true]);
    TraumaScreening::factory()->create(['campaign_id' => $trauma->id]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.results', [$this->tenant, $trauma->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('campaign.kindLabel', 'Guía I por acontecimiento')
            ->where('guide', null)
            ->where('results', null)
            ->where('trauma', ['answered' => 3, 'requiring' => 2]));
});

it('never shows a viewer the Guía I, not even counted', function (): void {
    TraumaScreening::factory()->create(['campaign_id' => $this->evaluation->id, 'requires_clinical_assessment' => true]);
    answeredBy($this->evaluation, 'nom035.guide-ii', 5, $this->plantArea);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.answered', 5)
            ->where('trauma', null));
});

it('gives HR no single answer: no answers and no response ids in what the page receives', function (): void {
    answeredBy($this->evaluation, 'nom035.guide-ii', 6, $this->plantArea);
    $ids = AnonymousResponse::query()->withoutGlobalScopes()->pluck('id')->all();

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id]))
        ->assertInertia(function (Assert $page) use ($ids): void {
            $props = json_encode($page->toArray()['props'], JSON_THROW_ON_ERROR);

            expect(propsHaveKey($page->toArray()['props'], 'answers'))->toBeFalse();

            foreach ($ids as $id) {
                expect($props)->not->toContain($id);
            }
        });
});

it('records in the audit trail who looked at the results', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id, 'area' => $this->sales->id]));

    expect(AuditEntry::query()->where('event', 'results.viewed')->sole())
        ->tenant_id->toBe($this->tenant->id)
        ->actor_id->toBe($this->hr->id)
        ->metadata->toBe(['campaign_id' => $this->evaluation->id]);
});

it('finds no campaign of another company, the same 404 as one that does not exist', function (): void {
    $other = Campaign::factory()->closed()->create();

    $this->actingAs($this->hr)->get(route('tenant.campaigns.results', [$this->tenant, $other->id]))->assertNotFound();
    $this->actingAs($this->hr)->get(route('tenant.campaigns.results', [$this->tenant, '00000000-0000-4000-8000-000000000000']))->assertNotFound();
    expect(AuditEntry::query()->where('event', 'results.viewed')->exists())->toBeFalse();
});

it('reads the results with a fixed number of queries, however many answered', function (int $answered): void {
    answeredBy($this->evaluation, 'nom035.guide-ii', $answered, $this->plantArea);
    answeredBy($this->evaluation, 'nom035.guide-ii', $answered, $this->sales);
    $this->actingAs($this->hr);
    $url = route('tenant.campaigns.results', [$this->tenant, $this->evaluation->id]);
    $this->get($url);

    $this->expectsDatabaseQueryCount(16);

    $this->get($url)->assertOk();
})->with([5, 15]);
