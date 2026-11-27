<?php

declare(strict_types=1);

use App\Modules\Attendance\Contracts\AttendanceActivity;
use App\Modules\Attendance\Data\CenterAttendanceActivity;
use App\Modules\Attendance\Models\AttendanceCenterMode;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Campaigns\Contracts\CampaignActivity;
use App\Modules\Campaigns\Data\CenterCampaignActivity;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Complaints\Contracts\ComplaintActivity;
use App\Modules\Complaints\Data\ComplaintCounts;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Engagement\Contracts\EngagementActivity;
use App\Modules\Engagement\Data\SurveyActivity;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Leave\Contracts\LeaveActivity;
use App\Modules\Leave\Data\LeaveCounts;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Contracts\WorkerAccessActivity;
use App\Modules\WorkerAccess\Data\AccessActivity;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    // The session became ready at 18:00 UTC; whatever is older belongs to the demo's story, not to the session.
    $this->since = CarbonImmutable::parse('2026-11-26 18:00:00', 'UTC');
    $this->travelTo($this->since->subSecond());
});

describe('CampaignActivity', function (): void {
    beforeEach(function (): void {
        $this->center = WorkCenter::factory()->create(['tenant_id' => $this->a->id]);
    });

    /** `$invited` people invited to `$campaign`, `$answered` of them with a guide completed, and one in the sample still waiting for its link. */
    function campaignWith(Campaign $campaign, int $invited, int $answered): Campaign
    {
        $people = Participant::factory()->count($invited)->create(['campaign_id' => $campaign->id]);
        Participant::factory()->create(['campaign_id' => $campaign->id, 'invited_at' => null, 'invitation_status' => null]);

        foreach ($people->take($answered) as $person) {
            ParticipantInstrument::factory()->completed()->create(['participant_id' => $person->id]);
            ParticipantInstrument::factory()->completed()->create(['participant_id' => $person->id, 'instrument' => 'nom035.guide-ii']);
        }

        return $campaign;
    }

    it('says nothing while the center has no evaluation', function (): void {
        workIn($this->a);

        expect(resolve(CampaignActivity::class)->atCenter($this->center->id))->toEqual(new CenterCampaignActivity(0, false, 0, 0, false, null));
    });

    it('tells a draft waits in the center', function (): void {
        $draft = Campaign::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id]);
        workIn($this->a);

        expect(resolve(CampaignActivity::class)->atCenter($this->center->id))->toEqual(new CenterCampaignActivity(1, false, 0, 0, false, $draft->id));
    });

    it('follows the most recent evaluation: how many were invited and how many answered, never who', function (): void {
        campaignWith(Campaign::factory()->closed()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id]), 2, 2);
        $this->travel(1)->minutes();
        $open = campaignWith(Campaign::factory()->active()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id]), 3, 1);
        workIn($this->a);

        expect(resolve(CampaignActivity::class)->atCenter($this->center->id))->toEqual(new CenterCampaignActivity(0, true, 3, 1, false, $open->id));
    });

    it('tells the evaluation closed', function (): void {
        $closed = campaignWith(Campaign::factory()->closed()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id]), 2, 2);
        workIn($this->a);

        expect(resolve(CampaignActivity::class)->atCenter($this->center->id))->toEqual(new CenterCampaignActivity(0, true, 2, 2, true, $closed->id));
    });

    it('counts only the session center and only NOM-035 evaluations', function (): void {
        Campaign::factory()->create(['tenant_id' => $this->a->id]);
        Campaign::factory()->active()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id, 'kind' => CampaignKind::SeatingSurvey]);
        Campaign::factory()->trauma()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id]);
        workIn($this->a);

        expect(resolve(CampaignActivity::class)->atCenter($this->center->id))->toEqual(new CenterCampaignActivity(0, false, 0, 0, false, null));
    });

    it('does not read another company', function (): void {
        $theirs = WorkCenter::factory()->create(['tenant_id' => $this->b->id]);
        campaignWith(Campaign::factory()->active()->create(['tenant_id' => $this->b->id, 'work_center_id' => $theirs->id]), 2, 1);
        workIn($this->a);

        expect(resolve(CampaignActivity::class)->atCenter($theirs->id))->toEqual(new CenterCampaignActivity(0, false, 0, 0, false, null));
    });
});

describe('AttendanceActivity', function (): void {
    beforeEach(function (): void {
        $this->center = WorkCenter::factory()->create(['tenant_id' => $this->a->id]);
    });

    /** A zone, a kiosk and a mark at `$centerId` of `$tenantId`, now. */
    function attendanceAt(string $tenantId, string $centerId): void
    {
        AttendanceZone::factory()->create(['tenant_id' => $tenantId, 'work_center_id' => $centerId]);
        KioskDevice::factory()->create(['tenant_id' => $tenantId, 'work_center_id' => $centerId]);
        AttendanceMark::factory()->create(['tenant_id' => $tenantId, 'work_center_id' => $centerId]);
    }

    it('counts only what happened after the session started', function (): void {
        $mode = AttendanceCenterMode::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id]);
        attendanceAt($this->a->id, $this->center->id);
        $this->travelTo($this->since);
        attendanceAt($this->a->id, $this->center->id);
        KioskDevice::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id, 'revoked_at' => now()]);
        $mark = AttendanceMark::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->center->id]);
        AttendanceMark::factory()->correcting($mark)->create();
        workIn($this->a);
        $activity = resolve(AttendanceActivity::class);

        expect($activity->atCenter($this->center->id, $this->since))->toEqual(new CenterAttendanceActivity(false, 1, 1, 2));

        $mode->update(['mode' => App\Modules\Attendance\Enums\CenterMode::Company]);

        expect($activity->atCenter($this->center->id, $this->since)->modeSet)->toBeTrue()
            ->and($activity->atCenter($this->center->id, $this->since->setTimezone('America/Mexico_City'))->marks)->toBe(2);
    });

    it('counts only the session center', function (): void {
        $this->travelTo($this->since);
        $other = WorkCenter::factory()->create(['tenant_id' => $this->a->id]);
        AttendanceCenterMode::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $other->id]);
        attendanceAt($this->a->id, $other->id);
        workIn($this->a);

        expect(resolve(AttendanceActivity::class)->atCenter($this->center->id, $this->since))->toEqual(new CenterAttendanceActivity(false, 0, 0, 0));
    });

    it('does not read another company', function (): void {
        $this->travelTo($this->since);
        $theirs = WorkCenter::factory()->create(['tenant_id' => $this->b->id]);
        AttendanceCenterMode::factory()->create(['tenant_id' => $this->b->id, 'work_center_id' => $theirs->id]);
        attendanceAt($this->b->id, $theirs->id);
        workIn($this->a);

        expect(resolve(AttendanceActivity::class)->atCenter($theirs->id, $this->since))->toEqual(new CenterAttendanceActivity(false, 0, 0, 0));
    });
});

describe('WorkerAccessActivity', function (): void {
    it('counts only what happened after the session started', function (): void {
        WorkerAccessLink::factory()->create(['tenant_id' => $this->a->id]);
        WorkerCredential::factory()->create(['tenant_id' => $this->a->id]);
        $this->travelTo($this->since);
        $reissued = WorkerAccessLink::factory()->create(['tenant_id' => $this->a->id]);
        WorkerAccessLink::factory()->create(['tenant_id' => $this->a->id, 'employee_id' => $reissued->employee_id]);
        WorkerAccessLink::factory()->create(['tenant_id' => $this->a->id]);
        WorkerAccessLink::factory()->requested()->create(['tenant_id' => $this->a->id]);
        WorkerCredential::factory()->create(['tenant_id' => $this->a->id]);
        WorkerCredential::factory()->create(['tenant_id' => $this->a->id, 'revoked_at' => now()]);
        workIn($this->a);

        expect(resolve(WorkerAccessActivity::class)->since($this->since))->toEqual(new AccessActivity(2, 1))
            ->and(resolve(WorkerAccessActivity::class)->since($this->since->setTimezone('America/Mexico_City')))->toEqual(new AccessActivity(2, 1));
    });

    it('does not read another company', function (): void {
        $this->travelTo($this->since);
        WorkerAccessLink::factory()->create(['tenant_id' => $this->b->id]);
        WorkerCredential::factory()->create(['tenant_id' => $this->b->id]);
        workIn($this->a);

        expect(resolve(WorkerAccessActivity::class)->since($this->since))->toEqual(new AccessActivity(0, 0));
    });
});
