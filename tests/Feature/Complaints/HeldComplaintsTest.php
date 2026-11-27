<?php

declare(strict_types=1);

use App\Modules\Complaints\Actions\AssignComplaint;
use App\Modules\Complaints\Actions\ReleaseHeldComplaints;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\CaseAccess;
use App\Modules\Complaints\Support\ComplaintStatistics;
use App\Modules\Complaints\Support\UnassignedCases;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

mutates(ReleaseHeldComplaints::class, UnassignedCases::class, ComplaintStatistics::class, AssignComplaint::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    workIn($this->tenant);
    $this->held = fn (string $day): Complaint => Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'received_on' => $day, 'held' => true]);
});

it('hides a held complaint from the handler until the command releases it and assigns it', function (): void {
    $complaint = ($this->held)('2026-11-19');

    expect(resolve(CaseAccess::class)->find($this->owner->id, $complaint->id))->toBeNull()
        ->and(resolve(UnassignedCases::class)->count())->toBe(0)
        ->and(resolve(ComplaintStatistics::class)->summary()['byStatus'])->each(fn ($row) => $row->count->toBe(0));

    $this->artisan('complaints:release-held')->expectsOutput('Quejas liberadas: 1')->assertSuccessful();

    expect($complaint->refresh()->held)->toBeFalse()
        ->and(ComplaintAssignment::query()->where('complaint_id', $complaint->id)->where('user_id', $this->owner->id)->count())->toBe(1)
        ->and(resolve(CaseAccess::class)->find($this->owner->id, $complaint->id))->not->toBeNull();
});

it('does not assign a held complaint when someone routes the unassigned ones by hand', function (): void {
    ($this->held)('2026-11-19');

    $this->actingAs($this->owner)->post('/admin/acme/complaints/unassigned/route')->assertRedirect();

    expect(ComplaintAssignment::query()->count())->toBe(0);
});

it('keeps holding what came today, and a released complaint stays out of the new day\'s cap', function (): void {
    $today = ($this->held)('2026-11-20');
    $yesterday = ($this->held)('2026-11-19');

    resolve(ReleaseHeldComplaints::class)->handle();

    expect($today->refresh()->held)->toBeTrue()
        ->and($yesterday->refresh()->held)->toBeFalse()
        ->and($yesterday->received_on->toDateString())->toBe('2026-11-19');
});

it('logs the release with a count only', function (): void {
    ($this->held)('2026-11-19');
    Log::spy();

    resolve(ReleaseHeldComplaints::class)->handle();

    Log::shouldHaveReceived('info')->with('complaint.released', ['count' => 1])->once();
});

it('releases the held complaints of every company, each in its own', function (): void {
    ($this->held)('2026-11-19');
    $other = Tenant::factory()->create();
    $channel = ComplaintChannel::factory()->forTenant($other->id)->active()->create();
    Complaint::factory()->create(['tenant_id' => $other->id, 'channel_id' => $channel->id, 'received_on' => '2026-11-19', 'held' => true]);

    expect(resolve(ReleaseHeldComplaints::class)->handle())->toBe(2)
        ->and(Complaint::query()->withoutGlobalScopes()->where('held', true)->count())->toBe(0);
});
