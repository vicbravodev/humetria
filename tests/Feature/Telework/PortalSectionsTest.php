<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Leave\Models\LeaveRequest;
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
const PORTAL_HOME_QUERIES = 25;

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

it('has no sections for a person with nothing in any tool', function (): void {
    expect(portalSections($this->token))->toHaveKeys(['team'])->and(array_keys(portalSections($this->token)))->toBe(['team']);
});

it('lists vacations and Ley Silla for a person who is not a teleworker, and not the telework tool', function (): void {
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    portalPlan(['vacations', 'seating']);

    $sections = portalSections($this->token);

    expect(array_keys($sections))->toBe(['vacations', 'seating'])
        ->and($sections['vacations']['href'])->toBe(route('portal.vacations.show', $this->token))
        ->and($sections['seating']['href'])->toBe(route('portal.seating.show', $this->token));
    $this->get(route('portal.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('canNotify', false));
});

it('puts the telework tool first, with the pending items of the person', function (): void {
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id]);

    $sections = portalSections($this->token);

    expect(array_keys($sections)[0])->toBe('telework')
        ->and($sections['telework']['href'])->toBe(route('portal.notices.show', $this->token))
        ->and(Teleworker::query()->count())->toBe(1);
});

it('drops the section of a tool the company no longer has', function (): void {
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    portalPlan(['seating']);

    expect(array_keys(portalSections($this->token)))->toBe(['seating']);
});

it('tells a manager how many requests of their team wait, and the policy that waits for them', function (): void {
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    $ben = Employee::factory()->reportingTo($this->ana)->create(['tenant_id' => $this->tenant->id]);
    LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $ben->id, 'approver_employee_id' => $this->ana->id]);
    Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

    $sections = portalSections($this->token);

    expect($sections['vacations']['pending'])->toBe(1)
        ->and($sections['policy']['href'])->toBe(route('portal.policy.show', $this->token))
        ->and($sections['policy']['pending'])->toBe(1);
});

it('offers the page of the center only while its link is active, and support only to who was told through the portal', function (): void {
    expect(portalSections($this->token))->not->toHaveKeys(['center', 'support']);

    $link = PageLink::factory()->create(['tenant_id' => $this->tenant->id]);
    $campaign = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    TraumaScreening::factory()->create(['campaign_id' => $campaign->id, 'employee_id' => $this->ana->id, 'requires_clinical_assessment' => true]);
    ScreeningReferral::factory()->create(['campaign_id' => $campaign->id, 'told' => HowTold::Portal]);

    $sections = portalSections($this->token);

    expect($sections['center']['href'])->toBe(route('workers.show', $link->token()))
        ->and($sections['support']['href'])->toBe(route('portal.support.show', $this->token));

    $link->forceFill(['disabled_at' => now()])->save();
    expect(portalSections($this->token))->not->toHaveKey('center');
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

    expect(array_keys(portalSections($this->token)))->toBe(['vacations', 'team', 'seating', 'center']);
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
