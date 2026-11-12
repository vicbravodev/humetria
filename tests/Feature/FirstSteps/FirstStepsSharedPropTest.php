<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\FirstSteps\Models\FirstStepsState;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/** What the tour costs a page the owner opens, in queries: measured, and the same whatever the company has loaded. */
const FIRST_STEPS_QUERIES = 13;

beforeEach(function (): void {
    config(['first_steps.enabled' => true]);
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
});

function firstStepsQueriesOf(User $user, string $url, bool $enabled): int
{
    config(['first_steps.enabled' => $enabled]);
    // The first request of a process warms the permission cache: measure the second.
    test()->actingAs($user)->get($url)->assertOk();
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->actingAs($user)->get($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('shares the steps with the owner inside the company', function (): void {
    $this->actingAs($this->owner)->get(route('tenant.members.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('firstSteps.steps', 5)
            ->where('firstSteps.welcomed', false)
            ->where('firstSteps.hidden', false));
});

it('shares null to hr and viewer, at no query', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);
    $url = route('tenant.dashboard', $this->tenant);

    $this->actingAs($user)->get($url)->assertInertia(fn (Assert $page) => $page->where('firstSteps', null));

    expect(firstStepsQueriesOf($user, $url, true))->toBe(firstStepsQueriesOf($user, $url, false));
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('shares null outside a company', function (): void {
    $this->actingAs(superadmin())->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('firstSteps', null));
});

it('shares null and writes completed_at once every step is done', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now());
    TenantAiSettings::factory()->create(['tenant_id' => $this->tenant->id, 'enabled' => false]);
    $center = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
    memberOf($this->tenant, TenantRole::Hr);
    $url = route('tenant.members.index', $this->tenant);

    $this->actingAs($this->owner)->get($url)->assertInertia(fn (Assert $page) => $page->where('firstSteps', null));
    $this->actingAs($this->owner)->get($url)->assertInertia(fn (Assert $page) => $page->where('firstSteps', null));

    $states = FirstStepsState::query()->withoutGlobalScopes()->where('user_id', $this->owner->id)->get();
    expect($states)->toHaveCount(1)->and($states->first()?->completed_at)->not->toBeNull();
});

it('keeps the two companies of one owner apart', function (): void {
    $other = Tenant::factory()->create();
    TenantMembership::factory()->for($other)->for($this->owner)->create();
    WorkCenter::factory()->create(['tenant_id' => $other->id]);

    $this->actingAs($this->owner)->get(route('tenant.members.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('firstSteps.steps.0.done', false)
            ->where('firstSteps.steps.0.href', "/admin/{$this->tenant->slug}/work-centers"));
    $this->actingAs($this->owner)->get(route('tenant.members.index', $other))
        ->assertInertia(fn (Assert $page) => $page
            ->where('firstSteps.steps.0.done', true)
            ->where('firstSteps.steps.0.href', "/admin/{$other->slug}/work-centers"));
});

it('never shares a name, an email or an answer', function (): void {
    $center = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
    $invitation = TenantInvitation::factory()->create(['tenant_id' => $this->tenant->id]);
    $shared = null;

    $this->actingAs($this->owner)->get(route('tenant.members.index', $this->tenant))
        ->assertInertia(function (Assert $page) use (&$shared): void {
            $shared = json_encode($page->toArray()['props']['firstSteps'], JSON_THROW_ON_ERROR);
        });

    expect($shared)->not->toContain($employee->name)->not->toContain($employee->email)
        ->not->toContain($invitation->email)->not->toContain($this->owner->email)->not->toContain($this->owner->name);
});

it('costs the same queries with one work center and with thirty', function (): void {
    $url = route('tenant.members.index', $this->tenant);
    WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    $one = firstStepsQueriesOf($this->owner, $url, true) - firstStepsQueriesOf($this->owner, $url, false);

    WorkCenter::factory()->count(29)->create(['tenant_id' => $this->tenant->id]);
    $thirty = firstStepsQueriesOf($this->owner, $url, true) - firstStepsQueriesOf($this->owner, $url, false);

    expect($one)->toBe(FIRST_STEPS_QUERIES)->and($thirty)->toBe(FIRST_STEPS_QUERIES);
});
