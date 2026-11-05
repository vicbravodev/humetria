<?php

declare(strict_types=1);

use App\Modules\Complaints\Contracts\ComplaintsToAcknowledge;
use App\Modules\Complaints\Data\ComplaintToAcknowledge;
use App\Modules\Complaints\Enums\ComplaintResolution;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\CompanyComplaintsToAcknowledge;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;

mutates(CompanyComplaintsToAcknowledge::class);

// The notice of a traumatic event takes the name of who sends it and the day it happened.
const NOTICE_OF_AN_EVENT = ['type' => ComplaintType::TraumaticEventNotice, 'identity_mode' => 'confidential', 'reporter_name' => 'Rosa Aguilar', 'occurred_on' => '2026-11-10'];

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['acknowledge_within_days' => 3]);
});

/**
 * A complaint of the company assigned to someone.
 *
 * @param  array<string, mixed>  $attributes
 */
function waitingFor(object $test, object $person, array $attributes = [], bool $ended = false): Complaint
{
    $complaint = Complaint::factory()->create(['tenant_id' => $test->tenant->id, 'channel_id' => $test->channel->id, ...$attributes]);
    $assignment = ComplaintAssignment::factory()->state(['tenant_id' => $test->tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $person->id]);
    ($ended ? $assignment->ended() : $assignment)->create();

    return $complaint;
}

it('counts the complaints assigned to the user that still wait for their acknowledgment', function (): void {
    workIn($this->tenant);
    waitingFor($this, $this->owner);
    waitingFor($this, $this->owner, ['received_on' => '2026-11-15']);
    // Acknowledged, in review, closed, no longer theirs, or someone else's: none of them waits for this user.
    waitingFor($this, $this->owner, ['acknowledged_at' => now()]);
    waitingFor($this, $this->owner, ['status' => ComplaintStatus::InReview, 'acknowledged_at' => now()]);
    waitingFor($this, $this->owner, ['status' => ComplaintStatus::Closed, 'resolution' => ComplaintResolution::MeasuresTaken, 'acknowledged_at' => now(), 'closed_at' => now(), 'closed_on' => now()->toDateString()]);
    waitingFor($this, $this->owner, ended: true);
    waitingFor($this, memberOf($this->tenant, TenantRole::Hr));

    expect(app(ComplaintsToAcknowledge::class)->count($this->owner->id))->toBe(2);
});

it('lists the soonest to fall due first with its kind and deadline, never what it says', function (): void {
    $later = waitingFor($this, $this->owner, ['type' => ComplaintType::UnfavorablePractice, 'received_on' => '2026-11-19']);
    $sooner = waitingFor($this, $this->owner, ['type' => ComplaintType::WorkplaceViolence, 'received_on' => '2026-11-18']);
    waitingFor($this, $this->owner, [...NOTICE_OF_AN_EVENT, 'received_on' => '2026-11-20']);
    workIn($this->tenant);

    $listed = app(ComplaintsToAcknowledge::class)->soonest($this->owner->id, 2);

    expect($listed)->toEqual([
        new ComplaintToAcknowledge($sooner->id, 'Una denuncia espera acuse de recibo', '2026-11-21'),
        new ComplaintToAcknowledge($later->id, 'Una queja espera acuse de recibo', '2026-11-22'),
    ])->and(json_encode($listed))->not->toContain('compañera');
});

it('names the notice of a traumatic event as a notice', function (): void {
    waitingFor($this, $this->owner, NOTICE_OF_AN_EVENT);
    workIn($this->tenant);

    expect(app(ComplaintsToAcknowledge::class)->soonest($this->owner->id, 5)[0]->title)->toBe('Un aviso de acontecimiento traumático espera acuse de recibo');
});
