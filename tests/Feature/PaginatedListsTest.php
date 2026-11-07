<?php

declare(strict_types=1);

use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Every list of records reaches its page as a Laravel paginator, never as a whole array (CLAUDE.md, «Listas»).
 * tests/Unit/PaginatedListsTest.php makes each page that takes a Paginated prop appear here.
 */

/** The keys of a LengthAwarePaginator as Inertia sends it. */
const PAGINATOR_KEYS = ['data', 'current_page', 'last_page', 'per_page', 'total', 'prev_page_url', 'next_page_url', 'links'];

/** A company with an owner signed in, a work center with people and a launched evaluation of it. */
function listsCompany(): array
{
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    test()->actingAs(memberOf($tenant, TenantRole::Owner));
    $plant = WorkCenter::factory()->for($tenant)->create();
    staffOf($tenant, $plant, 3);
    $campaign = Campaign::factory()->active()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

    return [$tenant, $plant, $campaign];
}

it('sends each list of records as a paginator', function (string $component, Closure $url, array $lists): void {
    $this->get($url())->assertOk()->assertInertia(function (Assert $page) use ($component, $lists): void {
        $page->component($component);

        foreach ($lists as $prop) {
            $page->has($prop, fn (Assert $list) => $list->hasAll(PAGINATOR_KEYS)->etc());
        }
    });
})->with([
    'Humi' => ['assistant/index', function () {
        [$tenant] = listsCompany();
        assistantConversation(ConversationParticipant::factory()->for(auth()->user())->create(['tenant_id' => $tenant->id]));

        return route('tenant.assistant.index', $tenant);
    }, ['conversations']],
    'the panel' => ['dashboard', fn () => route('tenant.dashboard', listsCompany()[0]), ['centers']],
    'the first-time setup' => ['tenancy/onboarding', fn () => route('tenant.work-centers.onboarding', listsCompany()[0]), ['centers']],
    'NOM-035' => ['campaigns/index', fn () => route('tenant.campaigns.index', listsCompany()[0]), ['events', 'closed', 'workCenters']],
    'an evaluation' => ['campaigns/show', function () {
        [$tenant, , $campaign] = listsCompany();

        return route('tenant.campaigns.show', [$tenant, $campaign->id]);
    }, ['people']],
    'a sampled draft' => ['campaigns/show', function () {
        [$tenant] = listsCompany();
        $large = WorkCenter::factory()->for($tenant)->create();
        staffOf($tenant, $large, 60);
        test()->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $large->id, 'population' => 'sample']);

        return route('tenant.campaigns.show', [$tenant, Campaign::query()->withoutGlobalScopes()->where('status', 'draft')->sole()->id]);
    }, ['sample']],
    'Guía I by event' => ['campaigns/trauma', function () {
        [$tenant, $plant] = listsCompany();

        return route('tenant.campaigns.trauma.create', [$tenant, 'work_center' => $plant->id]);
    }, ['people']],
    'Guía I channeling list' => ['results/screenings', function () {
        [$tenant, , $campaign] = listsCompany();

        return route('tenant.campaigns.screenings', [$tenant, $campaign->id]);
    }, ['people']],
    'the 7.7 report and its links' => ['results/report', function () {
        [$tenant, $plant] = listsCompany();
        $closed = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

        return route('tenant.campaigns.report', [$tenant, $closed->id]);
    }, ['shares']],
    'the Programas de intervención' => ['interventions/index', fn () => route('tenant.interventions.index', listsCompany()[0]), ['evaluations']],
    'a Programa de intervención' => ['interventions/show', function () {
        [$tenant] = listsCompany();
        $closed = closedEvaluationWith($tenant, nom035Answers('nom035.guide-ii', 'always'));
        workIn($tenant);
        resolve(App\Modules\Interventions\Actions\CreateProgram::class)->handle($closed->id, null);

        return route('tenant.campaigns.program', [$tenant, $closed->id]);
    }, ['actions']],
    'Ley Silla centers' => ['seating/index', function () {
        [$tenant] = listsCompany();

        return route('tenant.seating.index', $tenant);
    }, ['centers']],
    'Ley Silla positions of a center' => ['seating/center', function () {
        [$tenant, $plant] = listsCompany();

        return route('tenant.seating.center', [$tenant, $plant->id]);
    }, ['positions']],
    'Ley Silla documents of a center' => ['seating/documents', function () {
        [$tenant, $plant] = listsCompany();

        return route('tenant.seating.documents.index', [$tenant, $plant->id]);
    }, ['documents']],
    'Ley Silla plan of a center' => ['seating/plan', function () {
        [$tenant, $plant] = listsCompany();

        return route('tenant.seating.plan', [$tenant, $plant->id]);
    }, ['measures', 'agreements']],
    'time clock correction requests' => ['attendance/corrections', fn () => route('tenant.attendance.corrections.index', listsCompany()[0]), ['requests']],
    'Ley Silla seat reports of a center' => ['seating/reports', function () {
        [$tenant, $plant] = listsCompany();

        return route('tenant.seating.reports.index', [$tenant, $plant->id]);
    }, ['reports']],
    'Ley Silla assessments of a center' => ['seating/assessments', function () {
        [$tenant, $plant] = listsCompany();

        return route('tenant.seating.assessments.index', [$tenant, $plant->id]);
    }, ['people']],
    'Ley Silla seats of a center' => ['seating/seats', function () {
        [$tenant, $plant] = listsCompany();

        return route('tenant.seating.seats.index', [$tenant, $plant->id]);
    }, ['seats']],
    'Ley Silla people of a position' => ['seating/position', function () {
        [$tenant, $plant] = listsCompany();
        $position = App\Modules\Seating\Models\StandingPosition::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

        return route('tenant.seating.positions.show', [$tenant, $position->id]);
    }, ['people']],
    'NOM-037 centers' => ['telework/index', function () {
        [$tenant] = listsCompany();

        return route('tenant.telework.index', $tenant);
    }, ['centers']],
    'NOM-037 roster of a center' => ['telework/center', function () {
        [$tenant, $plant] = listsCompany();

        return route('tenant.telework.center', [$tenant, $plant->id]);
    }, ['people']],
    'checklists to review' => ['telework/reviews', function () {
        [$tenant, $plant] = listsCompany();
        App\Modules\Telework\Models\CommitteeAssignment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'user_id' => auth()->id()]);

        return route('tenant.telework.reviews.index', [$tenant, $plant->id]);
    }, ['applications']],
    'NOM-037 documents' => ['telework/documents', function () {
        [$tenant] = listsCompany();

        return route('tenant.telework.documents.index', $tenant);
    }, ['documents']],
    'NOM-037 acknowledgements' => ['telework/acknowledgements', function () {
        [$tenant] = listsCompany();
        $document = App\Modules\Telework\Models\TeleworkDocument::factory()->create(['tenant_id' => $tenant->id]);

        return route('tenant.telework.documents.acknowledgements.index', [$tenant, $document->id]);
    }, ['people']],
    'NOM-037 trainings and who is due' => ['telework/trainings', function () {
        [$tenant] = listsCompany();

        return route('tenant.telework.trainings.index', $tenant);
    }, ['trainings', 'people']],
    'NOM-037 notices to review' => ['telework/notices', function () {
        [$tenant] = listsCompany();
        Database\Factories\Telework\WorkerNoticeFactory::new()->create(['tenant_id' => $tenant->id]);

        return route('tenant.telework.notices.index', $tenant);
    }, ['notices']],
    'complaints assigned to a handler' => ['complaints/index', function () {
        [$tenant] = listsCompany();
        $channel = App\Modules\Complaints\Models\ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
        $complaint = App\Modules\Complaints\Models\Complaint::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);
        App\Modules\Complaints\Models\ComplaintAssignment::factory()->create(['tenant_id' => $tenant->id, 'complaint_id' => $complaint->id, 'user_id' => auth()->id()]);

        return route('tenant.complaints.index', $tenant);
    }, ['cases']],
    'a complaint\'s thread' => ['complaints/show', function () {
        [$tenant] = listsCompany();
        $channel = App\Modules\Complaints\Models\ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
        $complaint = App\Modules\Complaints\Models\Complaint::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);
        App\Modules\Complaints\Models\ComplaintAssignment::factory()->create(['tenant_id' => $tenant->id, 'complaint_id' => $complaint->id, 'user_id' => auth()->id()]);

        return route('tenant.complaints.cases.show', [$tenant, $complaint->id]);
    }, ['conversation', 'notes', 'log']],
    'notices a person sent' => ['telework/portal-notices', function () {
        [$tenant] = listsCompany();
        $employee = Database\Factories\Organization\EmployeeFactory::new()->create(['tenant_id' => $tenant->id]);
        $teleworker = Database\Factories\Telework\TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id]);
        Database\Factories\Telework\WorkerNoticeFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => $teleworker->id]);
        workIn($tenant);

        return route('portal.notices.show', resolve(App\Modules\Telework\Actions\IssuePortalLink::class)->handle($employee->id));
    }, ['notices']],
    'the disclosure board' => ['disclosure/index', fn () => route('tenant.disclosure.index', listsCompany()[0]), ['centers']],
    'the policy and its history' => ['disclosure/policy', fn () => route('tenant.disclosure.policy', listsCompany()[0]), ['history']],
    'the results for the people' => ['disclosure/results', function () {
        [$tenant, $plant] = listsCompany();
        Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

        return route('tenant.disclosure.results', $tenant);
    }, ['evaluations']],
    'the policy acknowledgments' => ['disclosure/dissemination', function () {
        [$tenant] = listsCompany();
        $policy = App\Modules\Disclosure\Models\Document::factory()->published()->policy()->create(['tenant_id' => $tenant->id]);

        return route('tenant.disclosure.dissemination', $tenant);
    }, ['people', 'records', 'centers']],
    'the public page of the workers' => ['disclosure-public/centers', function () {
        [$tenant] = listsCompany();
        WorkCenter::factory()->for($tenant)->create();
        workIn($tenant);

        return route('workers.show', (string) resolve(App\Modules\Disclosure\Actions\IssuePageLink::class)->handle(auth()->id() ?? 0)->token());
    }, ['centers']],
    'employees' => ['organization/employees', fn () => route('tenant.employees.index', listsCompany()[0]), ['employees']],
    'benefits' => ['compensation/benefits', fn () => route('tenant.compensation.benefits.index', listsCompany()[0]), ['benefits']],
    'tabulator' => ['compensation/tabulator', fn () => route('tenant.compensation.tabulator.index', listsCompany()[0]), ['grades']],
    'salary overrides' => ['compensation/overrides', fn () => route('tenant.compensation.overrides.index', listsCompany()[0]), ['people']],
    'compensation start' => ['compensation/index', fn () => route('tenant.compensation.index', listsCompany()[0]), ['scenarios']],
    'scenarios' => ['compensation/scenarios', fn () => route('tenant.compensation.scenarios.index', listsCompany()[0]), ['scenarios']],
    'users' => ['tenancy/members', fn () => route('tenant.members.index', listsCompany()[0]), ['members', 'invitations']],
    'work centers' => ['tenancy/work-centers', fn () => route('tenant.work-centers.index', listsCompany()[0]), ['workCenters']],
    'companies' => ['tenancy/companies', function () {
        $user = App\Models\User::factory()->create();
        App\Modules\Tenancy\Models\TenantMembership::factory()->for(Tenant::factory()->create())->for($user)->create();
        App\Modules\Tenancy\Models\TenantMembership::factory()->for(Tenant::factory()->create())->for($user)->create();
        test()->actingAs($user);

        return route('dashboard');
    }, ['companies']],
    'companies console' => ['tenancy/index', function () {
        signInAsSuperadmin();

        return route('companies.index');
    }, ['tenants']],
    'demo requests' => ['demos/index', function () {
        DemoRequest::factory()->create();
        signInAsSuperadmin();

        return route('demo-requests.index');
    }, ['demoRequests']],
    'passkeys' => ['settings/security', function () {
        test()->actingAs(App\Models\User::factory()->create())->withSession(['auth.password_confirmed_at' => time()]);

        return route('security.edit');
    }, ['passkeys']],
]);
