<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\DB;

/** The queries of each screen, which do not depend on how many centers or people there are. */
const BOARD_QUERIES = 30;
const WORKERS_QUERIES = 25;
const FOLLOW_UP_QUERIES = 28;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    workIn($this->tenant);
});

/** A center with a policy, its people, some of whom confirmed, and a poster. */
function centerWithDisclosure(object $test, int $people = 3): WorkCenter
{
    $center = WorkCenter::factory()->for($test->tenant)->create();
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => $test->tenant->id, 'version' => (int) Document::query()->max('version') + 1, 'published_by' => $test->hr->id]);
    DocumentCenter::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $center->id]);

    foreach (range(1, $people) as $number) {
        $person = Employee::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $center->id]);
        Acknowledgment::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => $policy->id, 'employee_id' => $person->id, ...($number === 1 ? ['channel' => 'portal', 'acknowledged_at' => now()] : [])]);
    }

    DisseminationRecord::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $center->id]);

    return $center;
}

/** How many queries the request makes. */
function queriesOf(Closure $request): int
{
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $request();

    return $queries;
}

it('reads the board in a fixed number of queries, however many centers it has', function (): void {
    centerWithDisclosure($this);
    $url = route('tenant.disclosure.index', $this->tenant);
    // The first request warms what is cached (the permissions): only the steady state is counted.
    $this->actingAs($this->hr)->get($url);
    $few = queriesOf(fn () => $this->actingAs($this->hr)->get($url)->assertOk());

    foreach (range(1, 7) as $ignored) {
        centerWithDisclosure($this, 4);
    }
    $many = queriesOf(fn () => $this->actingAs($this->hr)->get($url)->assertOk());

    expect($many)->toBe($few);
    // The number itself is pinned: a query added by accident shows up here.
    expect($few)->toBe(BOARD_QUERIES);
});

it('reads the public page of a center in a fixed number of queries, whatever its people and its versions', function (): void {
    $token = (string) resolve(IssuePageLink::class)->handle($this->hr->id)->token();
    $center = centerWithDisclosure($this, 2);
    $this->get(route('workers.center', [$token, $center->id]));
    $few = queriesOf(fn () => $this->get(route('workers.center', [$token, $center->id]))->assertOk());

    $other = centerWithDisclosure($this, 30);
    centerWithDisclosure($this, 30);
    $many = queriesOf(fn () => $this->get(route('workers.center', [$token, $other->id]))->assertOk());

    expect($many)->toBe($few);
    expect($few)->toBe(WORKERS_QUERIES);
});

it('reads the follow-up of the acknowledgments in a fixed number of queries, whatever the people', function (): void {
    centerWithDisclosure($this, 3);
    $url = route('tenant.disclosure.dissemination', $this->tenant);
    $this->actingAs($this->hr)->get($url);
    $few = queriesOf(fn () => $this->actingAs($this->hr)->get($url)->assertOk());

    centerWithDisclosure($this, 14);
    $many = queriesOf(fn () => $this->actingAs($this->hr)->get($url)->assertOk());

    expect($many)->toBe($few);
    expect($few)->toBe(FOLLOW_UP_QUERIES);
});
