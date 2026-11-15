<?php

declare(strict_types=1);

use App\Modules\Complaints\Contracts\ComplaintChannels;
use App\Modules\Complaints\Data\ChannelData;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\PublishResults;
use App\Modules\Disclosure\Support\ComplaintChannelFeed;
use App\Modules\Disclosure\Support\ProgramMeasureFeed;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Results\Contracts\ProgramOutlines;
use App\Modules\Results\Data\ProgramOutline;
use App\Modules\Results\Data\ProgramOutlineAction;
use App\Modules\Results\Data\ProgramOutlineItem;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ProgramMeasureFeed::class, ComplaintChannelFeed::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    // Fixed names: the privacy assertions look for «Laura» and «Pedro», which a random company name can contain.
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'legal_name' => 'Acme S.A. de C.V.', 'trade_name' => 'Acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    staffOf($this->tenant, $this->plant, 20);
    workIn($this->tenant);
    $this->token = (string) resolve(IssuePageLink::class)->handle($this->hr->id)->token();
});

/** The public page props of the plant. @return array<string, mixed> */
function publicProps(object $test): array
{
    return $test->get(route('workers.center', [$test->token, $test->plant->id]))->viewData('page')['props'];
}

/** A closed evaluation of the plant with a Programa; its actions are given by `$actions` (attributes of ProgramAction). */
function plantProgram(object $test, array $actions): string
{
    static $number = 0;
    [$campaign] = closedEvaluationInAreas($test->tenant, ['Área '.++$number => 12]);
    $campaign->update(['work_center_id' => $test->plant->id]);
    $program = Program::factory()->create(['tenant_id' => $test->tenant->id, 'campaign_id' => $campaign->id]);
    $item = ProgramItem::factory()->create(['tenant_id' => $test->tenant->id, 'program_id' => $program->id]);

    foreach ($actions as $attributes) {
        ProgramAction::factory()->create(['tenant_id' => $test->tenant->id, 'program_id' => $program->id, 'item_id' => $item->id, ...$attributes]);
    }

    return $campaign->id;
}

it('shows the measures of the Programa of the published evaluation, only when RH said so, as a whitelist of fields', function (): void {
    $campaign = plantProgram($this, [
        ['title' => 'Revisar cargas de trabajo', 'kind' => ActionKind::WorkOrganization, 'responsible_name' => 'Laura Méndez', 'description' => 'Hablar con Pedro Gómez sobre sus turnos', 'due_on' => '2026-12-15'],
        ['title' => 'Campaña contra el acoso', 'kind' => ActionKind::ViolencePrevention, 'due_on' => '2027-01-10', 'status' => 'in_progress'],
    ]);
    resolve(PublishResults::class)->handle($campaign, $this->hr->id, showMeasures: true);

    $measures = publicProps($this)['measures'];

    expect($measures['c'])->toBe([['title' => 'Revisar cargas de trabajo', 'level' => 1, 'status' => 'planned', 'statusLabel' => 'Planeada', 'dueOn' => '15 dic 2026']])
        ->and($measures['b'])->toBe([['title' => 'Campaña contra el acoso', 'level' => 1, 'status' => 'in_progress', 'statusLabel' => 'En curso', 'dueOn' => '10 ene 2027']])
        ->and(json_encode(publicProps($this)))->not->toContain('Laura')->not->toContain('Pedro')->not->toContain('responsible')->not->toContain('description');
});

it('shows no measure unless RH chose to, and not once it withdraws the results', function (): void {
    $campaign = plantProgram($this, [['title' => 'Revisar cargas de trabajo']]);
    resolve(PublishResults::class)->handle($campaign, $this->hr->id);

    expect(publicProps($this)['measures'])->toBe(['b' => [], 'c' => []]);

    App\Modules\Disclosure\Models\ResultPublication::query()->update(['show_measures' => true]);
    expect(publicProps($this)['measures']['c'])->toHaveCount(1);

    resolve(App\Modules\Disclosure\Actions\WithdrawResults::class)->handle($campaign, $this->hr->id);
    expect(publicProps($this)['measures'])->toBe(['b' => [], 'c' => []]);
});

it('leaves out what is not for everybody: actions for areas, the third level and the cancelled', function (): void {
    $campaign = plantProgram($this, [
        ['title' => 'Solo para Producción', 'subjects' => 'areas'],
        ['title' => 'Atención clínica', 'level' => 3, 'kind' => ActionKind::ClinicalCare, 'professional' => 'psychologist'],
        ['title' => 'Cancelada', 'status' => 'cancelled', 'cancelled_reason' => 'x'],
        ['title' => 'Del grupo', 'level' => 2, 'kind' => ActionKind::Training],
    ]);
    resolve(PublishResults::class)->handle($campaign, $this->hr->id, showMeasures: true);

    expect(array_column(publicProps($this)['measures']['c'], 'title'))->toBe(['Del grupo']);
});

it('reads the measures of the evaluation shown, not those of an older one', function (): void {
    $older = plantProgram($this, [['title' => 'Medida vieja']]);
    App\Modules\Campaigns\Models\Campaign::query()->whereKey($older)->update(['closed_at' => '2026-03-01 18:00:00']);
    resolve(PublishResults::class)->handle($older, $this->hr->id, showMeasures: true);
    $newer = plantProgram($this, [['title' => 'Medida nueva']]);
    resolve(PublishResults::class)->handle($newer, $this->hr->id, showMeasures: true);

    expect(array_column(publicProps($this)['measures']['c'], 'title'))->toBe(['Medida nueva']);
});

it('copies only the whitelisted fields of whatever the Programa gives, however much more it says', function (): void {
    $action = new ProgramOutlineAction(title: 'Taller de comunicación', kind: 'Capacitación', level: 'Segundo nivel', subjects: 'Todo el centro de trabajo', startsOn: '1 dic 2026', dueOn: '20 dic 2026', responsible: 'Laura Méndez', status: 'Planeada', professional: 'Psicólogo', overdue: true, kindKey: 'training', levelValue: 2, statusKey: 'planned', wholeCenter: true);
    $this->app->bind(ProgramOutlines::class, fn () => new class($action) implements ProgramOutlines
    {
        public function __construct(private ProgramOutlineAction $action) {}

        public function forCampaign(string $campaignId): ?ProgramOutline
        {
            return new ProgramOutline('En curso', 'in_progress', ['done' => 0, 'total' => 1], 'Muy alto', 'Laura Méndez', '20 nov 2026', [new ProgramOutlineItem('Calificación final', 'Final', 'Muy alto', 'El área de Ventas de Beto está al límite', 'Hecha el 1 dic', [$this->action])]);
        }
    });

    $measures = resolve(ProgramMeasureFeed::class)->forCampaign('any');

    expect($measures)->toHaveCount(1)
        ->and(get_object_vars($measures[0]))->toBe(['title' => 'Taller de comunicación', 'inciso' => 'c', 'level' => 2, 'status' => 'planned', 'statusLabel' => 'Planeada', 'dueOn' => '20 dic 2026'])
        ->and(json_encode($measures))->not->toContain('Laura')->not->toContain('Beto')->not->toContain('Psicólogo');
});

it('has no measures for an evaluation without a Programa', function (): void {
    expect(resolve(ProgramMeasureFeed::class)->forCampaign('0194c1de-0000-7000-8000-000000000000'))->toBe([]);
});

it('shows the channel for complaints with its link, whether it takes anonymous ones and what it accepts, and nothing more', function (): void {
    $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['procedure' => 'Procedimiento interno secreto', 'acknowledge_within_days' => 3]);

    $props = publicProps($this);

    expect($props['channel'])->toBe([
        'url' => url('/c/'.$channel->access_code),
        'anonymousAllowed' => true,
        'categories' => ['Quejas por prácticas opuestas a un entorno favorable', 'Denuncias de violencia laboral', 'Avisos de acontecimientos traumáticos severos'],
    ])->and(json_encode($props))->not->toContain('secreto')->not->toContain('acknowledge');
});

it('shows no channel while it is not active', function (string $state): void {
    $factory = ComplaintChannel::factory()->forTenant($this->tenant->id);
    $state === 'none' ? null : ($state === 'paused' ? $factory->paused()->create() : $factory->create());

    expect(publicProps($this)['channel'])->toBeNull();
})->with(['none', 'set up but not activated', 'paused']);

it('does not repeat a link that leaves the app, and drops categories it does not know', function (): void {
    $this->app->bind(ComplaintChannels::class, fn () => new class implements ComplaintChannels
    {
        public function current(): ChannelData
        {
            return new ChannelData(true, 'https://evil.example/c/abc', CarbonImmutable::now(), ['workplace_violence', 'made_up'], false, 3, 30, 'x');
        }
    });

    expect(resolve(ComplaintChannelFeed::class)->current())->toBeNull();

    $this->app->bind(ComplaintChannels::class, fn () => new class implements ComplaintChannels
    {
        public function current(): ChannelData
        {
            return new ChannelData(true, url('/c/abc'), CarbonImmutable::now(), ['workplace_violence', 'made_up'], false, 3, 30, 'x');
        }
    });

    $channel = resolve(ComplaintChannelFeed::class)->current();

    expect($channel?->anonymousAllowed)->toBeFalse()->and($channel?->categories)->toBe(['Denuncias de violencia laboral']);
});

it('counts the channel and the measures as what the board asks for in 5.7 b, c and d, never in place of a text but joined to it', function (): void {
    $campaign = plantProgram($this, [['title' => 'Revisar cargas de trabajo', 'kind' => ActionKind::ViolencePrevention]]);
    resolve(PublishResults::class)->handle($campaign, $this->hr->id, showMeasures: true);
    ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();

    $this->actingAs($this->hr)->get(route('tenant.disclosure.index', $this->tenant))->assertInertia(function (Assert $page): void {
        $rows = collect(collect($page->toArray()['props']['centers']['data'])->firstWhere('name', 'Planta Norte')['requirements'])->keyBy('code');
        // The page is active, so what exists is delivered; nothing was written by hand.
        expect($rows['D4']['state']['value'])->toBe('met')->and($rows['D6']['state']['value'])->toBe('met')->and($rows['D5']['state']['value'])->toBe('missing');
    });
});
