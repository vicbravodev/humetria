<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\RouteUnassignedComplaints;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\CaseAccess;
use App\Modules\Complaints\Support\TicketSigner;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Events\MemberRemoved;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RouteUnassignedComplaints::class, CaseAccess::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 03:30:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->outsider = memberOf($this->tenant, TenantRole::Complaints);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    $this->plant = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    workIn($this->tenant);
});

/** Designates the people as company-wide handlers. */
function handlersAre(object $test, object ...$people): void
{
    foreach ($people as $person) {
        ComplaintHandler::factory()->company($test->tenant->id)->create(['user_id' => $person->id]);
    }
}

/** Submits a complaint through the public form and returns it. */
function receive(object $test, RespondentKind $kind, ?string $centerId = null): Complaint
{
    $before = Complaint::query()->pluck('id')->all();
    $ticket = resolve(TicketSigner::class)->issue($test->channel->access_code);
    Carbon::setTestNow(now()->addSeconds(10));
    $test->postJson('/c/'.$test->channel->access_code, [
        'ticket' => $ticket,
        'type' => 'workplace_violence',
        'description' => 'Una queja de prueba lo bastante larga para pasar.',
        'respondent_kind' => $kind->value,
        'work_center_id' => $centerId,
        'identity_mode' => 'anonymous',
    ])->assertCreated();

    return Complaint::query()->whereNotIn('id', $before)->firstOrFail();
}

/** A complaint nobody holds, as one is left when its handlers go. */
function unheld(object $test, RespondentKind $kind): Complaint
{
    return Complaint::factory()->create(['tenant_id' => $test->tenant->id, 'channel_id' => $test->channel->id, 'respondent_kind' => $kind]);
}

/** @return list<int> */
function assignedTo(Complaint $complaint): array
{
    $ids = ComplaintAssignment::query()->where('complaint_id', $complaint->id)->whereNull('ended_on')->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
    sort($ids);

    return $ids;
}

it('assigns a new complaint to every handler its conflict leaves, for the whole company', function (): void {
    handlersAre($this, $this->owner, $this->hr, $this->outsider);

    $complaint = receive($this, RespondentKind::DirectSupervisor);

    $expected = [$this->owner->id, $this->hr->id, $this->outsider->id];
    sort($expected);
    expect(assignedTo($complaint))->toBe($expected);
});

it('keeps a complaint against HR from the handlers who are HR', function (): void {
    handlersAre($this, $this->owner, $this->hr, $this->outsider);

    $complaint = receive($this, RespondentKind::Hr);

    $expected = [$this->owner->id, $this->outsider->id];
    sort($expected);
    expect(assignedTo($complaint))->toBe($expected);
});

it('keeps a complaint against the management from the handlers who are the owner', function (): void {
    handlersAre($this, $this->owner, $this->hr);

    $complaint = receive($this, RespondentKind::Management);

    expect(assignedTo($complaint))->toBe([$this->hr->id]);
});

it('gives the complaint of a center only to who handles that center or the company', function (): void {
    $other = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    handlersAre($this, $this->owner);
    ComplaintHandler::factory()->forCenter($this->tenant->id, $this->plant->id)->create(['user_id' => $this->hr->id]);
    ComplaintHandler::factory()->forCenter($this->tenant->id, $other->id)->create(['user_id' => $this->outsider->id]);

    $complaint = receive($this, RespondentKind::Coworker, $this->plant->id);

    $expected = [$this->owner->id, $this->hr->id];
    sort($expected);
    expect(assignedTo($complaint))->toBe($expected);
});

it('records the assignment of the system with the day and nobody who made it', function (): void {
    handlersAre($this, $this->owner);

    $complaint = receive($this, RespondentKind::Coworker);

    $assignment = ComplaintAssignment::query()->where('complaint_id', $complaint->id)->sole();
    expect($assignment->assigned_by)->toBeNull()
        ->and($assignment->assigned_on->toDateString())->toBe('2026-11-19')
        ->and($assignment->ended_on)->toBeNull()
        ->and($assignment->end_reason)->toBeNull()
        ->and($assignment->notified_position)->toBeNull();
    expect(AuditEntry::query()->count())->toBe(0);
});

it('never stores the hour of an assignment', function (): void {
    expect(Schema::hasColumn('complaint_assignments', 'assigned_at'))->toBeFalse()
        ->and(Schema::hasColumn('complaint_assignments', 'created_at'))->toBeFalse();
});

it('lets a handler designated afterwards see none of the complaints that came before', function (): void {
    handlersAre($this, $this->owner);
    $complaint = receive($this, RespondentKind::Coworker);

    handlersAre($this, $this->hr);

    expect(resolve(CaseAccess::class)->find($this->hr->id, $complaint->id))->toBeNull()
        ->and(resolve(CaseAccess::class)->find($this->owner->id, $complaint->id)?->id)->toBe($complaint->id);
});

it('finds a case only for a person assigned to it, and none of another company', function (): void {
    handlersAre($this, $this->owner);
    $complaint = receive($this, RespondentKind::Coworker);
    $theirs = Tenant::factory()->create();
    $theirChannel = ComplaintChannel::factory()->forTenant($theirs->id)->active()->create();
    $foreign = Complaint::factory()->create(['tenant_id' => $theirs->id, 'channel_id' => $theirChannel->id]);
    $access = resolve(CaseAccess::class);

    expect($access->find($this->owner->id, $complaint->id)?->id)->toBe($complaint->id)
        ->and($access->find($this->outsider->id, $complaint->id))->toBeNull()
        ->and($access->find($this->owner->id, $foreign->id))->toBeNull()
        ->and($access->find($this->owner->id, 'not-a-uuid'))->toBeNull();
});

it('does not let an ended assignment open the case', function (): void {
    handlersAre($this, $this->owner);
    $complaint = receive($this, RespondentKind::Coworker);
    ComplaintAssignment::query()->where('complaint_id', $complaint->id)->update(['ended_on' => '2026-11-19', 'end_reason' => 'recused']);

    expect(resolve(CaseAccess::class)->find($this->owner->id, $complaint->id))->toBeNull();
});

it('ends the assignments of a handler whose designation ends, and leaves the case without anyone to be counted', function (): void {
    Notification::fake();
    handlersAre($this, $this->owner);
    $only = ComplaintHandler::query()->where('user_id', $this->owner->id)->sole();
    $complaint = receive($this, RespondentKind::Coworker);
    handlersAre($this, $this->hr);
    $this->channel->forceFill(['paused_at' => now()])->save();
    Log::spy();

    $this->actingAs($this->hr)->delete(route('tenant.complaints.handlers.destroy', [$this->tenant, $only->id]))->assertRedirect();

    $assignment = ComplaintAssignment::query()->where('complaint_id', $complaint->id)->sole();
    expect($assignment->ended_on?->toDateString())->toBe('2026-11-19')->and($assignment->end_reason)->toBe('handler_ended');
    expect(assignedTo($complaint))->toBe([]);
});

it('ends the assignments of a person who lost their access to the company', function (): void {
    handlersAre($this, $this->owner, $this->hr);
    $complaint = receive($this, RespondentKind::Coworker);

    event(new MemberRemoved($this->tenant->id, $this->hr->id));

    $ended = ComplaintAssignment::query()->where('complaint_id', $complaint->id)->where('user_id', $this->hr->id)->sole();
    expect($ended->end_reason)->toBe('member_removed')->and(assignedTo($complaint))->toBe([$this->owner->id]);
});

it('assigns again the complaints nobody holds, by the same rules and with who manages the channel as the author', function (): void {
    Log::spy();
    handlersAre($this, $this->owner, $this->hr, $this->outsider);
    $complaint = unheld($this, RespondentKind::Hr);

    $this->actingAs($this->hr)->post(route('tenant.complaints.unassigned.route', $this->tenant))->assertRedirect();

    $expected = [$this->owner->id, $this->outsider->id];
    sort($expected);
    expect(assignedTo($complaint))->toBe($expected);
    $assignment = ComplaintAssignment::query()->where('complaint_id', $complaint->id)->where('user_id', $this->owner->id)->sole();
    expect($assignment->assigned_by)->toBe($this->hr->id)
        ->and($assignment->assigned_on->toDateString())->toBe('2026-11-19');
    expect(AuditEntry::query()->where('event', 'complaint.assigned')->count())->toBe(2);
    Log::shouldHaveReceived('info')->with('complaints.assignment_changed', Mockery::on(fn (array $context): bool => $context['reason'] === 'unassigned_routed' && $context['count'] === 2))->once();
});

it('does not assign a complaint against HR to a manager who is HR, even by hand', function (): void {
    handlersAre($this, $this->hr);
    $complaint = unheld($this, RespondentKind::Hr);

    $this->actingAs($this->hr)->post(route('tenant.complaints.unassigned.route', $this->tenant))->assertRedirect();

    expect(assignedTo($complaint))->toBe([]);
});

it('leaves alone the complaints somebody holds and the ones already closed', function (): void {
    handlersAre($this, $this->owner);
    $held = receive($this, RespondentKind::Coworker);
    $closed = Complaint::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, 'respondent_kind' => RespondentKind::Coworker]);
    handlersAre($this, $this->hr);

    $this->actingAs($this->hr)->post(route('tenant.complaints.unassigned.route', $this->tenant))->assertRedirect();

    expect(assignedTo($held))->toBe([$this->owner->id])
        ->and(assignedTo($closed))->toBe([]);
});

it('counts the complaints nobody holds for who manages the channel, without saying which', function (): void {
    handlersAre($this, $this->hr);
    unheld($this, RespondentKind::Hr);
    unheld($this, RespondentKind::Hr);
    receive($this, RespondentKind::Coworker);

    $this->actingAs($this->hr)->get(route('tenant.complaints.channel.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('unassigned', 2));
});

it('keeps one company\'s assignments out of another\'s', function (): void {
    $theirs = Tenant::factory()->create();
    $channel = ComplaintChannel::factory()->forTenant($theirs->id)->active()->create();
    $complaint = Complaint::factory()->create(['tenant_id' => $theirs->id, 'channel_id' => $channel->id]);
    $assignment = ComplaintAssignment::factory()->create(['tenant_id' => $theirs->id, 'complaint_id' => $complaint->id]);

    expect(ComplaintAssignment::query()->find($assignment->id))->toBeNull();
});

it('lets only who manages the channel route the complaints nobody holds', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))->post(route('tenant.complaints.unassigned.route', $this->tenant))->assertForbidden();
})->with([TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints]);
