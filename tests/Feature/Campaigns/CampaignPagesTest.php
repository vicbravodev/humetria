<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Support\CampaignPages;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CampaignPages::class, App\Modules\Campaigns\Support\CampaignPeople::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
});

/** Whether `$key` appears as a key anywhere in `$value`. */
function hasKeyDeep(mixed $value, string $key): bool
{
    if (! is_array($value)) {
        return false;
    }

    foreach ($value as $name => $item) {
        if ($name === $key || hasKeyDeep($item, $key)) {
            return true;
        }
    }

    return false;
}

/** A launched campaign of the plant whose `$completed` of `$invited` people answered `$instrument`. */
function campaignWithAnswers(Tenant $tenant, WorkCenter $workCenter, string $instrument, int $invited, int $completed, array $attributes = []): Campaign
{
    $campaign = Campaign::factory()->active('2026-11-14')->create(['tenant_id' => $tenant->id, 'work_center_id' => $workCenter->id, ...$attributes]);

    foreach (range(1, $invited) as $index) {
        $participant = Participant::factory()->create(['campaign_id' => $campaign->id]);
        ParticipantInstrument::factory()->create([
            'participant_id' => $participant->id,
            'instrument' => $instrument,
            ...($index <= $completed ? ['status' => InstrumentStatus::Completed, 'completed_at' => now()] : []),
        ]);
    }

    return $campaign;
}

it('lists open evaluations with their progress, and each work center with its last evaluation and next due date', function (): void {
    Carbon::setTestNow('2026-10-29 12:00:00');
    // Each participant brings their own person of the plant: 60 people.
    $open = campaignWithAnswers($this->tenant, $this->plant, 'nom035.guide-iii', 60, 21, [
        'instruments' => ['nom035.guide-i', 'nom035.guide-iii'], 'workers' => 60, 'population' => Population::Sample, 'sample_size' => 52, 'quotas' => ['female' => 52],
    ]);
    $warehouse = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Almacén']);
    staffOf($this->tenant, $warehouse, 20);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $warehouse->id, 'closed_at' => '2024-10-20 18:00:00']);
    WorkCenter::factory()->for($this->tenant)->archived()->create();

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/index')
            ->where('open.total', 1)
            ->where('open.data.0', [
                'id' => $open->id,
                'workCenter' => 'Planta Norte',
                'kind' => 'nom035_evaluation',
                'kindLabel' => 'Evaluación NOM-035',
                'status' => 'active',
                'statusLabel' => 'En curso',
                'instruments' => ['Guía I', 'Guía III'],
                'closesOn' => '14 nov 2026',
                'closedOn' => null,
                'progress' => ['completed' => 21, 'target' => 52, 'againstSample' => true],
            ])
            ->where('closed.total', 1)
            ->where('closed.data.0.status', 'closed')
            ->where('closed.data.0.closedOn', '20 oct 2024')
            ->where('workCenters.total', 2)
            ->where('workCenters.data', [
                [
                    'id' => $warehouse->id, 'name' => 'Almacén', 'workers' => 20, 'guides' => ['I', 'II'],
                    'lastEvaluatedOn' => '20 oct 2024', 'dueOn' => '20 oct 2026', 'overdue' => true,
                    'openCampaignId' => null, 'eligible' => true,
                ],
                [
                    'id' => $this->plant->id, 'name' => 'Planta Norte', 'workers' => 60, 'guides' => ['I', 'III'],
                    'lastEvaluatedOn' => null, 'dueOn' => null, 'overdue' => false,
                    'openCampaignId' => $open->id, 'eligible' => false,
                ],
            ]));
});

it('measures a Guía II evaluation against everyone invited, and a draft not at all', function (): void {
    campaignWithAnswers($this->tenant, $this->plant, 'nom035.guide-ii', 18, 7);
    $this->travel(1)->minutes();
    Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('open.data.0.progress', null)
            ->where('open.data.1.progress', ['completed' => 7, 'target' => 18, 'againstSample' => false]));
});

it('pages open and closed evaluations and the work centers, each on its own', function (): void {
    foreach (range(1, 13) as $index) {
        $center = WorkCenter::factory()->for($this->tenant)->create(['name' => sprintf('Centro %02d', $index)]);
        Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
        Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
        Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
    }

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.index', [$this->tenant, 'open' => 2, 'closed' => 2, 'centers' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('open.total', 13)->has('open.data', 1)
            ->where('closed.total', 26)->has('closed.data', 1)
            ->where('workCenters.total', 14)->has('workCenters.data', 14)
            ->where('workCenters.data.0.name', 'Centro 01'));
});

it('reads the evaluations page in a fixed number of queries', function (int $centers): void {
    foreach (range(1, $centers) as $index) {
        $center = WorkCenter::factory()->for($this->tenant)->create();
        campaignWithAnswers($this->tenant, $center, 'nom035.guide-ii', 2, 1);
        Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
    }
    $this->actingAs($this->hr);
    $url = route('tenant.campaigns.index', $this->tenant);
    $this->get($url);

    $this->expectsDatabaseQueryCount(18);

    $this->get($url)->assertOk();
})->with([2, 20]);

it('shows a sampled draft with its quotas and who was drawn, to those who manage it only', function (): void {
    staffOf($this->tenant, $this->plant, female: 30, male: 30);
    $this->actingAs($this->hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id, 'population' => 'sample']);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('campaign.workers', 60)
            ->where('campaign.population', 'sample')
            ->where('campaign.populationLabel', 'Muestra')
            ->where('campaign.sampleSize', 53)
            ->where('campaign.quotas', [
                ['gender' => 'female', 'label' => 'Mujeres', 'count' => 27],
                ['gender' => 'male', 'label' => 'Hombres', 'count' => 26],
            ])
            ->where('sample.total', 53)
            ->has('sample.data', 25)
            ->has('sample.data.0', fn (Assert $person) => $person->hasAll(['id', 'name', 'area', 'gender'])));

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id, 'page' => 3]))
        ->assertInertia(fn (Assert $page) => $page->has('sample.data', 3));

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('sample', null)->where('launch', null));
});

it('offers to launch a draft from tomorrow in Mexico City, suggesting two weeks', function (): void {
    // 03:00 UTC on the 1st is still 31 October in Mexico City.
    Carbon::setTestNow('2026-11-01 03:00:00');
    $draft = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $draft->id]))
        ->assertInertia(fn (Assert $page) => $page->where('launch', ['min' => '2026-11-01', 'suggested' => '2026-11-14']));
});

it('counts each guide of a launched campaign: answered, pending, without a link and cancelled', function (): void {
    $campaign = campaignWithAnswers($this->tenant, $this->plant, 'nom035.guide-ii', 4, 1);
    personIn($campaign, 'Sin correo', ['nom035.guide-i' => InstrumentStatus::Pending, 'nom035.guide-ii' => InstrumentStatus::Pending], ['invited_at' => null, 'invitation_status' => App\Modules\Campaigns\Enums\InvitationStatus::NoEmail], ['email' => null, 'mobile' => '5512345678']);
    personIn($campaign, 'Baja', ['nom035.guide-i' => InstrumentStatus::Completed, 'nom035.guide-ii' => InstrumentStatus::Cancelled]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('instruments', [
            ['key' => 'nom035.guide-i', 'title' => 'Guía I', 'invited' => 2, 'completed' => 1, 'pending' => 0, 'unreachable' => 1, 'cancelled' => 0],
            ['key' => 'nom035.guide-ii', 'title' => 'Guía II', 'invited' => 5, 'completed' => 1, 'pending' => 3, 'unreachable' => 1, 'cancelled' => 1],
        ]));
});

it('never gives the pages an answer', function (): void {
    $campaign = campaignWithAnswers($this->tenant, $this->plant, 'nom035.guide-ii', 3, 3);
    AnonymousResponse::factory()->create(['campaign_id' => $campaign->id]);

    foreach ([route('tenant.campaigns.index', $this->tenant), route('tenant.campaigns.show', [$this->tenant, $campaign->id])] as $url) {
        $this->actingAs($this->hr)->get($url)->assertInertia(function (Assert $page): void {
            expect(hasKeyDeep($page->toArray()['props'], 'answers'))->toBeFalse();
        });
    }
});

/**
 * A person of the plant in `$campaign` with one guide per entry of `$guides` (instrument → status).
 *
 * @param  array<string, InstrumentStatus>  $guides
 */
function personIn(Campaign $campaign, string $name, array $guides, array $participant = [], array $employee = []): Participant
{
    $person = App\Modules\Organization\Models\Employee::factory()->create(['tenant_id' => $campaign->tenant_id, 'work_center_id' => $campaign->work_center_id, 'name' => $name, ...$employee]);
    $invited = Participant::factory()->create(['campaign_id' => $campaign->id, 'employee_id' => $person->id, ...$participant]);

    foreach ($guides as $instrument => $status) {
        ParticipantInstrument::factory()->create(['participant_id' => $invited->id, 'instrument' => $instrument, 'status' => $status, ...match ($status) {
            InstrumentStatus::Completed => ['completed_at' => now()],
            InstrumentStatus::Cancelled => ['cancelled_at' => now()],
            InstrumentStatus::Pending => [],
        }]);
    }

    return $invited;
}

/** The names on the people page of `$campaign` for `$query`, as `$user` sees it. */
function peopleNames(Tests\TestCase $test, App\Models\User $user, Campaign $campaign, array $query = []): array
{
    $names = [];
    $test->actingAs($user)
        ->get(route('tenant.campaigns.show', ['tenant' => $test->tenant, 'campaign' => $campaign->id, ...$query]))
        ->assertInertia(function (Assert $page) use (&$names): void {
            $names = array_column($page->toArray()['props']['people']['data'], 'name');
        });

    return $names;
}

it('lists who still has a guide pending, by name, with where each stands, only to those who manage it', function (): void {
    $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $production = App\Modules\Organization\Models\Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $ana = personIn($campaign, 'Ana López', ['nom035.guide-i' => InstrumentStatus::Completed, 'nom035.guide-ii' => InstrumentStatus::Pending], employee: ['area_id' => $production->id]);
    personIn($campaign, 'Beatriz Soto', ['nom035.guide-i' => InstrumentStatus::Completed, 'nom035.guide-ii' => InstrumentStatus::Completed]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['status' => 'pending', 'guide' => null, 'area' => null, 'q' => ''])
            ->where('people.total', 1)
            ->where('people.data', [[
                'id' => $ana->id, 'name' => 'Ana López', 'area' => 'Producción', 'reachable' => true, 'inSample' => false,
                'guides' => [
                    ['key' => 'nom035.guide-i', 'title' => 'Guía I', 'status' => 'completed', 'statusLabel' => 'Contestó'],
                    ['key' => 'nom035.guide-ii', 'title' => 'Guía II', 'status' => 'pending', 'statusLabel' => 'Pendiente'],
                ],
            ]])
            ->where('minDeadline', now()->setTimezone('America/Mexico_City')->addDay()->format('Y-m-d')));

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('people', null)->where('standings', null)->where('minDeadline', null)->has('instruments', 2));
});

it('narrows the people by where they stand, counting each standing', function (): void {
    $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    personIn($campaign, 'Pendiente', ['nom035.guide-i' => InstrumentStatus::Pending]);
    personIn($campaign, 'Sin correo', ['nom035.guide-i' => InstrumentStatus::Pending], ['invited_at' => null, 'invitation_status' => App\Modules\Campaigns\Enums\InvitationStatus::NoEmail], ['email' => null, 'mobile' => '5512345678']);
    personIn($campaign, 'Contestó', ['nom035.guide-i' => InstrumentStatus::Completed, 'nom035.guide-ii' => InstrumentStatus::Completed]);
    personIn($campaign, 'Baja', ['nom035.guide-i' => InstrumentStatus::Completed, 'nom035.guide-ii' => InstrumentStatus::Cancelled]);
    personIn($campaign, 'Falló', ['nom035.guide-i' => InstrumentStatus::Pending], ['invitation_status' => App\Modules\Campaigns\Enums\InvitationStatus::Failed]);
    personIn($campaign, 'En cola', ['nom035.guide-i' => InstrumentStatus::Pending], ['invitation_status' => App\Modules\Campaigns\Enums\InvitationStatus::Queued, 'invited_at' => null]);

    expect(peopleNames($this, $this->hr, $campaign, ['status' => 'unreachable']))->toBe(['Sin correo'])
        ->and(peopleNames($this, $this->hr, $campaign, ['status' => 'failed']))->toBe(['Falló'])
        ->and(peopleNames($this, $this->hr, $campaign, ['status' => 'answered']))->toBe(['Contestó'])
        ->and(peopleNames($this, $this->hr, $campaign, ['status' => 'cancelled']))->toBe(['Baja'])
        ->and(peopleNames($this, $this->hr, $campaign, ['status' => 'all']))->toBe(['Baja', 'Contestó', 'En cola', 'Falló', 'Pendiente', 'Sin correo'])
        // Something else is the default: who has a link sent or on its way.
        ->and(peopleNames($this, $this->hr, $campaign, ['status' => 'nadie']))->toBe(['En cola', 'Pendiente']);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('standings', [
            ['value' => 'pending', 'label' => 'Pendientes', 'count' => 2],
            ['value' => 'unreachable', 'label' => 'Sin correo', 'count' => 1],
            ['value' => 'failed', 'label' => 'Envío fallido', 'count' => 1],
            ['value' => 'answered', 'label' => 'Contestaron', 'count' => 1],
            ['value' => 'cancelled', 'label' => 'Canceladas', 'count' => 1],
            ['value' => 'all', 'label' => 'Todas', 'count' => 6],
        ]));
});

it('narrows the people by guide, area and text, and keeps the standing counts to the guide', function (): void {
    $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $sales = App\Modules\Organization\Models\Area::factory()->create(['tenant_id' => $this->tenant->id]);
    personIn($campaign, 'Ana López', ['nom035.guide-i' => InstrumentStatus::Completed, 'nom035.guide-ii' => InstrumentStatus::Pending], employee: ['area_id' => $sales->id]);
    personIn($campaign, 'Luis Mora', ['nom035.guide-i' => InstrumentStatus::Pending, 'nom035.guide-ii' => InstrumentStatus::Pending]);

    expect(peopleNames($this, $this->hr, $campaign, ['guide' => 'nom035.guide-i']))->toBe(['Luis Mora'])
        ->and(peopleNames($this, $this->hr, $campaign, ['guide' => 'nom035.guide-i', 'status' => 'answered']))->toBe(['Ana López'])
        ->and(peopleNames($this, $this->hr, $campaign, ['guide' => 'nom035.guide-iii']))->toBe(['Ana López', 'Luis Mora'])
        ->and(peopleNames($this, $this->hr, $campaign, ['area' => $sales->id]))->toBe(['Ana López'])
        ->and(peopleNames($this, $this->hr, $campaign, ['q' => 'MORA']))->toBe(['Luis Mora']);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id, 'guide' => 'nom035.guide-i', 'area' => $sales->id, 'q' => ' ana ']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['status' => 'pending', 'guide' => 'nom035.guide-i', 'area' => ['value' => $sales->id, 'label' => $sales->name], 'q' => 'ana'])
            ->where('standings.0.count', 1)
            ->where('standings.3.count', 1));
});

it('pages the people 25 at a time', function (): void {
    $campaign = campaignWithAnswers($this->tenant, $this->plant, 'nom035.guide-ii', 26, 0);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 26)->where('people.current_page', 2)->has('people.data', 1));
});

it('reads the progress of a campaign of any size in a fixed number of queries', function (int $people): void {
    $campaign = campaignWithAnswers($this->tenant, $this->plant, 'nom035.guide-ii', $people, 1);
    $this->actingAs($this->hr);
    $url = route('tenant.campaigns.show', [$this->tenant, $campaign->id]);
    $this->get($url);

    // The same with 3 or 30 people: nothing is read person by person.
    $this->expectsDatabaseQueryCount(19);

    $this->get($url)->assertOk();
})->with([3, 30]);

it('offers replacements for each gender that has people left outside the sample', function (): void {
    staffOf($this->tenant, $this->plant, female: 40, male: 20);
    Illuminate\Support\Facades\Notification::fake();
    $this->actingAs($this->hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id, 'population' => 'sample']);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();
    $this->actingAs($this->hr)->post(route('tenant.campaigns.launch', [$this->tenant, $campaign->id]), ['closes_on' => now()->addWeek()->toDateString()]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('replacementPool', [
            ['gender' => 'female', 'label' => 'Mujeres', 'available' => 5],
            ['gender' => 'male', 'label' => 'Hombres', 'available' => 2],
        ]));
});

it('says how the sending of the last batch of links goes, while it goes', function (): void {
    $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'invitation_batch' => 'a31a4b00-0000-4000-8000-000000000001']);
    $batch = ['invitation_batch' => $campaign->invitation_batch];
    personIn($campaign, 'Enviada', ['nom035.guide-i' => InstrumentStatus::Pending], $batch);
    personIn($campaign, 'Falló', ['nom035.guide-i' => InstrumentStatus::Pending], [...$batch, 'invitation_status' => App\Modules\Campaigns\Enums\InvitationStatus::Failed]);
    $queued = personIn($campaign, 'En cola', ['nom035.guide-i' => InstrumentStatus::Pending], [...$batch, 'invitation_status' => App\Modules\Campaigns\Enums\InvitationStatus::Queued]);
    personIn($campaign, 'De antes', ['nom035.guide-i' => InstrumentStatus::Pending]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('sending', ['done' => 2, 'total' => 3]));

    $queued->update(['invitation_status' => App\Modules\Campaigns\Enums\InvitationStatus::Sent]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('sending', null));
});
