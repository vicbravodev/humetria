<?php

declare(strict_types=1);

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
    'NOM-035' => ['campaigns/index', fn () => route('tenant.campaigns.index', listsCompany()[0]), ['open', 'closed', 'workCenters']],
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
    'employees' => ['organization/employees', fn () => route('tenant.employees.index', listsCompany()[0]), ['employees']],
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
