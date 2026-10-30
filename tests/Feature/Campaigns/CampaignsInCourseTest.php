<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Campaigns\Contracts\OpenCampaigns;
use App\Modules\Campaigns\Data\CampaignsInCourse;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\CompanyOpenCampaigns;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CompanyOpenCampaigns::class, HandleInertiaRequests::class);

beforeEach(function (): void {
    // Friday 30 Oct 2026, noon in Mexico City.
    Carbon::setTestNow('2026-10-30 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
});

it('counts the campaigns in course and tells how far the soonest to close has gone', function (): void {
    // Launched 27 oct, last day 10 nov: 3 of its 14 days gone by, 11 left.
    Campaign::factory()->active('2026-11-10')->create(['tenant_id' => $this->tenant->id, 'launched_at' => '2026-10-27 18:00:00']);
    Campaign::factory()->active('2026-12-01')->create(['tenant_id' => $this->tenant->id]);
    Campaign::factory()->create(['tenant_id' => $this->tenant->id]);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
    Campaign::factory()->active('2026-10-31')->create(); // Another company's.
    workIn($this->tenant);

    expect(resolve(OpenCampaigns::class)->inCourse())->toEqual(new CampaignsInCourse(2, 11, 21));
});

it('has nothing in course without an active campaign', function (): void {
    Campaign::factory()->create(['tenant_id' => $this->tenant->id]);
    workIn($this->tenant);

    expect(resolve(OpenCampaigns::class)->inCourse())->toEqual(new CampaignsInCourse(0, null, 0));
});

it('shares what is in course on every page of a company, in one query', function (): void {
    WorkCenter::factory()->for($this->tenant)->create();
    Campaign::factory()->active('2026-11-10')->create(['tenant_id' => $this->tenant->id, 'launched_at' => '2026-10-27 18:00:00']);
    $this->actingAs(ownerOf($this->tenant));

    $campaignQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$campaignQueries): void {
        $campaignQueries += str_contains($query->sql, 'from "campaigns"') ? 1 : 0;
    });

    $this->get(route('tenant.work-centers.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('inCourse', ['count' => 1, 'daysLeft' => 11, 'elapsed' => 21]));

    expect($campaignQueries)->toBe(1);
});

it('shares nothing in course outside a company, without asking the database', function (): void {
    $campaignQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$campaignQueries): void {
        $campaignQueries += str_contains($query->sql, 'from "campaigns"') ? 1 : 0;
    });
    $this->actingAs(superadmin());

    $this->get(route('companies.index'))->assertInertia(fn (Assert $page) => $page->where('inCourse', null));

    expect($campaignQueries)->toBe(0);
});
