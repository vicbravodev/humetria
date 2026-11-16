<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Enums\HowTold;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\TeleworkerFactory;
use Inertia\Testing\AssertableInertia as Assert;

/** Opening the link once (the middleware and the page share it) and what each listed tool reads for its section. */
const PORTAL_HOME_QUERIES = 17;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    $this->token = resolve(IssuePortalLink::class)->handle($this->ana->id);
});

/** @return array<string, mixed> The `sections` of the home page, keyed by their key. */
function portalSections(string $token): array
{
    $sections = [];
    test()->get(route('portal.show', $token))->assertOk()->assertInertia(function (Assert $page) use (&$sections): void {
        $sections = collect($page->toArray()['props']['sections'])->keyBy('key')->all();
    });

    return $sections;
}

function portalPlan(array $modules): void
{
    resolve(CompanyModules::class)->syncPlan(test()->tenant->id, $modules, CarbonImmutable::now(config('app.display_timezone')));
}

it('has only «Mi jornada» for a person with nothing in any other tool («Mi equipo» is in the person\'s start, in their session)', function (): void {
    expect(array_keys(portalSections($this->token)))->toBe(['attendance']);
});

it('leaves vacations and Ley Silla out of the start by link: they are in the person\'s start, in their session', function (): void {
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    portalPlan(['vacations', 'seating']);

    expect(portalSections($this->token))->toBe([]);
});

it('puts the telework tool first, with the pending items of the person, at their page with no credential', function (): void {
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id]);

    $sections = portalSections($this->token);

    expect(array_keys($sections)[0])->toBe('telework')
        ->and($sections['telework']['href'])->toBe(route('portal.telework.show', $this->tenant->slug))
        ->and(Teleworker::query()->count())->toBe(1);
});

it('drops the section of a tool the company no longer has', function (): void {
    portalPlan(['attendance']);

    expect(array_keys(portalSections($this->token)))->toBe(['attendance']);

    portalPlan(['seating']);

    expect(portalSections($this->token))->toBe([]);
});

it('leaves the policy and the page of the center out of the start by link: they are in the person\'s start, in their session', function (): void {
    Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    PageLink::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(portalSections($this->token))->not->toHaveKeys(['policy', 'center']);
});

it('offers support only to who was told through the portal', function (): void {
    expect(portalSections($this->token))->not->toHaveKey('support');

    $campaign = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    TraumaScreening::factory()->create(['campaign_id' => $campaign->id, 'employee_id' => $this->ana->id, 'requires_clinical_assessment' => true]);
    ScreeningReferral::factory()->create(['campaign_id' => $campaign->id, 'told' => HowTold::Portal]);

    expect(portalSections($this->token)['support']['href'])->toBe(route('portal.support.show', $this->token));
});

it('offers the climate page once the company published what it will do', function (): void {
    $survey = climateResultsSurvey($this->tenant, 12);
    $survey->update(['invited' => 20, 'results_published_at' => now()]);
    Invitation::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id]);
    App\Modules\Engagement\Models\Commitment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id]);

    expect(portalSections($this->token)['climate']['href'])->toBe(route('portal.climate.show', $this->token));
});

it('offers the feedback page to a manager whose group is shown', function (): void {
    $other = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5], $other->id => [6, 1]]);

    expect(portalSections($this->token)['feedback']['href'])->toBe(route('portal.feedback.show', $this->token))
        ->and(portalSections(resolve(IssuePortalLink::class)->handle($other->id)))->toHaveKey('feedback');
});

it('keeps the order fixed and sends nothing of another person', function (): void {
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    PageLink::factory()->create(['tenant_id' => $this->tenant->id]);
    $bea = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bea Paz']);
    Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $bea->id]);

    expect(array_keys(portalSections($this->token)))->toBe(['attendance']);
    $this->get(route('portal.show', $this->token))->assertInertia(function (Assert $page): void {
        expect(json_encode($page->toArray()['props'], JSON_THROW_ON_ERROR))->not->toContain('Bea Paz')->not->toContain($this->token.'x');
    });
});

it('reads the home with the same queries whatever the people the team has', function (int $people): void {
    Employee::factory()->count($people)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'manager_id' => $this->ana->id]);
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    portalPlan(['vacations', 'seating']);

    $this->expectsDatabaseQueryCount(PORTAL_HOME_QUERIES);

    $this->get(route('portal.show', $this->token))->assertOk();
})->with([1, 12]);
