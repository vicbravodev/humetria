<?php

declare(strict_types=1);

use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\ComplaintStatistics;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ComplaintStatistics::class);

// The channel has no launch: its summary follows the company's minimum of people per group as it is now.

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
});

/** Files `$count` workplace-violence cases in the company's channel. */
function fileComplaints(mixed $test, int $count): void
{
    Complaint::factory()->count($count)->create(['tenant_id' => $test->tenant->id, 'channel_id' => $test->channel->id, 'type' => 'workplace_violence']);
}

/** The channel's summary as the owner sees it. */
function channelSummaryOf(mixed $test): Closure
{
    return fn (Closure $assert) => $test->actingAs($test->owner)->get(route('tenant.complaints.channel.show', $test->tenant))->assertInertia(fn (Assert $page) => $assert($page));
}

it('hides a count one under the company minimum and shows it at the minimum', function (int $minimum): void {
    $this->tenant->forceFill(['min_group' => $minimum])->save();
    fileComplaints($this, $minimum - 1);

    channelSummaryOf($this)(fn (Assert $page) => $page->where('summary.minimum', $minimum)->where('summary.total', null)->where('summary.byType.1.count', null));

    fileComplaints($this, 1);

    channelSummaryOf($this)(fn (Assert $page) => $page->where('summary.minimum', $minimum)->where('summary.total', $minimum)->where('summary.byType.1.count', $minimum));
})->with([3, 12]);

it('shows every exact count when the company set no minimum', function (): void {
    $this->tenant->forceFill(['min_group' => 1])->save();
    fileComplaints($this, 1);

    channelSummaryOf($this)(fn (Assert $page) => $page
        ->where('summary.minimum', 1)
        ->where('summary.total', 1)
        ->where('summary.byType.1.count', 1)
        ->where('summary.byStatus.0.count', 1));
});

it('keeps the platform minimum of 5 when another company set none', function (): void {
    Tenant::factory()->create(['min_group' => 1]);
    fileComplaints($this, 4);

    channelSummaryOf($this)(fn (Assert $page) => $page->where('summary.minimum', 5)->where('summary.total', null));
});
