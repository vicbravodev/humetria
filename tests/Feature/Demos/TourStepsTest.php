<?php

declare(strict_types=1);

use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Attendance\Enums\CenterMode;
use App\Modules\Attendance\Models\AttendanceCenterMode;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Demos\Support\TourCatalog;
use App\Modules\Demos\Support\Tours\Answers;
use App\Modules\Demos\Support\Tours\AttendanceTour;
use App\Modules\Demos\Support\Tours\BaseTour;
use App\Modules\Demos\Support\Tours\ComplaintsTour;
use App\Modules\Demos\Support\Tours\EngagementTour;
use App\Modules\Demos\Support\Tours\HumiTour;
use App\Modules\Demos\Support\Tours\LeaveTour;
use App\Modules\Demos\Support\Tours\Nom035Tour;
use App\Modules\Demos\Support\Tours\SharedAccessActivity;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerCredential;

/*
 * Each step of a tour is done when the real fact happened in the demo, read from the modules' public contracts; the step
 * that counts something tells it live («Llegaron 3 de 5 respuestas»). What happened before the session belongs to the
 * demo's story and does not count.
 */

mutates(TourCatalog::class, BaseTour::class, Nom035Tour::class, AttendanceTour::class, EngagementTour::class, ComplaintsTour::class, LeaveTour::class, HumiTour::class, SharedAccessActivity::class, Answers::class);

beforeEach(function (): void {
    $this->demo = Tenant::factory()->demo()->create();
    $this->center = WorkCenter::factory()->create(['tenant_id' => $this->demo->id]);
    $this->session = DemoSession::query()->forceCreate([
        'tours' => array_map(fn (TourKey $tour): string => $tour->value, TourKey::cases()),
        'status' => DemoSessionStatus::Active,
        'work_center_id' => $this->center->id,
        'started_at' => now()->subMinute(),
        'ready_at' => now(),
    ]);
    $this->travel(1)->second();
    workIn($this->demo);
});

/** @return array<string, StepState> the state of each step of `$tour`, by key */
function statesOf(TourKey $tour, DemoSession $session): array
{
    $catalog = resolve(TourCatalog::class);
    $states = [];

    foreach ($catalog->for([$tour]) as $step) {
        if ($step->tour === $tour) {
            $states[$step->key] = $catalog->state($step, $session);
        }
    }

    return $states;
}

/** @return list<string> the keys of the steps of `$tour` already done */
function tourDoneOf(TourKey $tour, DemoSession $session): array
{
    return array_keys(array_filter(statesOf($tour, $session), fn (StepState $state): bool => $state->done));
}

/** `$invited` people of `$campaign` invited, `$answered` of them with their guides completed. */
function evaluationWith(Campaign $campaign, int $invited, int $answered): void
{
    $people = Participant::factory()->count($invited)->create(['campaign_id' => $campaign->id]);

    foreach ($people->take($answered) as $person) {
        ParticipantInstrument::factory()->completed()->create(['participant_id' => $person->id]);
        ParticipantInstrument::factory()->completed()->create(['participant_id' => $person->id, 'instrument' => 'nom035.guide-ii']);
    }
}

it('marks the base steps done as the center gets its people', function (): void {
    Employee::factory()->create(['tenant_id' => $this->demo->id]);

    expect(tourDoneOf(TourKey::Base, $this->session))->toBe([]);

    $this->session->recordVisit('tenant.work-centers.index');
    Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);
    Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);

    expect(tourDoneOf(TourKey::Base, $this->session))->toBe(['base.center', 'base.people'])
        ->and(statesOf(TourKey::Base, $this->session)['base.people']->live)->toBe('2 personas en el centro');
});

it('marks each nom035 step done as the evaluation advances', function (): void {
    expect(tourDoneOf(TourKey::Nom035, $this->session))->toBe([]);

    $campaign = Campaign::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);

    expect(tourDoneOf(TourKey::Nom035, $this->session))->toBe(['nom035.draft']);

    $campaign->delete();
    $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);
    evaluationWith($campaign, 5, 0);

    expect(tourDoneOf(TourKey::Nom035, $this->session))->toBe(['nom035.draft', 'nom035.launch'])
        ->and(statesOf(TourKey::Nom035, $this->session)['nom035.answers']->live)->toBe('Llegaron 0 de 5 respuestas');

    Participant::query()->where('campaign_id', $campaign->id)->delete();
    evaluationWith($campaign, 5, 3);

    expect(tourDoneOf(TourKey::Nom035, $this->session))->toBe(['nom035.draft', 'nom035.launch'])
        ->and(statesOf(TourKey::Nom035, $this->session)['nom035.answers']->live)->toBe('Llegaron 3 de 5 respuestas');

    Participant::query()->where('campaign_id', $campaign->id)->delete();
    evaluationWith($campaign, 5, 5);

    expect(tourDoneOf(TourKey::Nom035, $this->session))->toBe(['nom035.draft', 'nom035.launch', 'nom035.answers']);

    $campaign->update(['status' => App\Modules\Campaigns\Enums\CampaignStatus::Closed, 'closed_at' => now()]);
    $this->session->recordVisit('tenant.campaigns.results');

    expect(tourDoneOf(TourKey::Nom035, $this->session))->toBe(['nom035.draft', 'nom035.launch', 'nom035.answers', 'nom035.close', 'nom035.results']);

    $this->session->recordVisit('tenant.campaigns.screenings');

    expect(tourDoneOf(TourKey::Nom035, $this->session))->toHaveCount(6);
});

it('counts the answers step done when the evaluation closes', function (int $answered): void {
    evaluationWith(Campaign::factory()->closed()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]), 5, $answered);

    expect(statesOf(TourKey::Nom035, $this->session)['nom035.answers']->done)->toBeTrue();
})->with([
    'with some answers' => 2,
    // Closing with nobody answering must not leave the guide stuck on a step that can no longer happen.
    'with none' => 0,
]);

it('does not count an evaluation nobody was invited to as answered', function (): void {
    Campaign::factory()->active()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);

    expect(statesOf(TourKey::Nom035, $this->session)['nom035.answers']->done)->toBeFalse();
});

it('does not count an evaluation of another center', function (): void {
    Campaign::factory()->closed()->create(['tenant_id' => $this->demo->id]);

    expect(tourDoneOf(TourKey::Nom035, $this->session))->toBe([]);
});

it('marks each attendance step done as the center is set up and marked', function (): void {
    // A mode written before the session (the center's default) is not the presenter choosing one.
    $this->travel(-1)->hour();
    $mode = AttendanceCenterMode::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);
    $this->travelBack();

    expect(tourDoneOf(TourKey::Attendance, $this->session))->toBe([]);

    $mode->update(['mode' => CenterMode::Company]);
    AttendanceZone::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);
    KioskDevice::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);

    expect(tourDoneOf(TourKey::Attendance, $this->session))->toBe(['attendance.mode', 'attendance.zone', 'attendance.kiosk']);

    WorkerAccessLink::factory()->count(2)->create(['tenant_id' => $this->demo->id]);
    WorkerCredential::factory()->create(['tenant_id' => $this->demo->id]);

    expect(tourDoneOf(TourKey::Attendance, $this->session))->toBe(['attendance.mode', 'attendance.zone', 'attendance.kiosk', 'attendance.access', 'attendance.key'])
        ->and(statesOf(TourKey::Attendance, $this->session)['attendance.access']->live)->toBe('2 accesos enviados')
        ->and(statesOf(TourKey::Attendance, $this->session)['attendance.key']->live)->toBe('1 llave creada');

    AttendanceMark::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);
    $this->session->recordVisit('tenant.attendance.report');

    expect(tourDoneOf(TourKey::Attendance, $this->session))->toHaveCount(7)
        ->and(statesOf(TourKey::Attendance, $this->session)['attendance.mark']->live)->toBe('1 marca en el centro');
});

it('does not count what the demo had before the session', function (): void {
    $this->travel(-1)->hour();
    AttendanceZone::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);
    WorkerCredential::factory()->create(['tenant_id' => $this->demo->id]);
    Survey::factory()->create(['tenant_id' => $this->demo->id]);
    LeaveRequest::factory()->approved()->create(['tenant_id' => $this->demo->id]);
    $this->travelBack();

    expect(tourDoneOf(TourKey::Attendance, $this->session))->toBe([])
        ->and(tourDoneOf(TourKey::Engagement, $this->session))->toBe([])
        ->and(tourDoneOf(TourKey::Leave, $this->session))->toBe([]);
});

it('marks each engagement step done as the climate survey advances', function (): void {
    expect(tourDoneOf(TourKey::Engagement, $this->session))->toBe([]);

    $survey = Survey::factory()->create(['tenant_id' => $this->demo->id]);

    expect(tourDoneOf(TourKey::Engagement, $this->session))->toBe(['engagement.create']);

    $survey->delete();
    $survey = Survey::factory()->active()->create(['tenant_id' => $this->demo->id, 'invited' => 3]);
    Invitation::factory()->answered()->count(2)->create(['survey_id' => $survey->id]);

    expect(tourDoneOf(TourKey::Engagement, $this->session))->toBe(['engagement.create', 'engagement.launch'])
        ->and(statesOf(TourKey::Engagement, $this->session)['engagement.answers']->live)->toBe('Llegaron 2 de 3 respuestas');

    Invitation::factory()->answered()->create(['survey_id' => $survey->id]);
    $this->session->recordVisit('tenant.climate.results');

    expect(tourDoneOf(TourKey::Engagement, $this->session))->toBe(['engagement.create', 'engagement.launch', 'engagement.answers', 'engagement.results']);
});

it('marks each complaints step done as a complaint arrives and is acknowledged', function (): void {
    $channel = ComplaintChannel::factory()->active()->create(['tenant_id' => $this->demo->id]);

    expect(tourDoneOf(TourKey::Complaints, $this->session))->toBe([]);

    $complaint = Complaint::factory()->create(['tenant_id' => $this->demo->id, 'channel_id' => $channel->id, 'received_on' => now('America/Mexico_City')->toDateString()]);

    expect(tourDoneOf(TourKey::Complaints, $this->session))->toBe(['complaints.receive'])
        ->and(statesOf(TourKey::Complaints, $this->session)['complaints.receive']->live)->toBe('1 denuncia recibida');

    $complaint->update(['acknowledged_at' => now()]);

    expect(tourDoneOf(TourKey::Complaints, $this->session))->toBe(['complaints.receive', 'complaints.acknowledge']);
});

it('marks each leave step done as a person asks for days and HR approves them', function (): void {
    expect(tourDoneOf(TourKey::Leave, $this->session))->toBe([]);

    WorkerCredential::factory()->create(['tenant_id' => $this->demo->id]);

    expect(tourDoneOf(TourKey::Leave, $this->session))->toBe(['leave.key']);

    $request = LeaveRequest::factory()->create(['tenant_id' => $this->demo->id]);

    expect(tourDoneOf(TourKey::Leave, $this->session))->toBe(['leave.key', 'leave.request']);

    $request->delete();
    LeaveRequest::factory()->approved()->create(['tenant_id' => $this->demo->id]);
    $this->session->recordVisit('tenant.vacations.balances.index');

    expect(tourDoneOf(TourKey::Leave, $this->session))->toBe(['leave.key', 'leave.request', 'leave.approve', 'leave.balance']);
});

it('marks the humi step done when the presenter asked Humi', function (): void {
    $owner = memberOf($this->demo, TenantRole::Owner);
    $this->actingAs($owner);

    expect(tourDoneOf(TourKey::Humi, $this->session))->toBe([]);

    $participant = ConversationParticipant::factory()->for($owner)->create(['tenant_id' => $this->demo->id]);
    AiUsage::factory()->create(['tenant_id' => $this->demo->id, 'participant_id' => $participant->id]);

    expect(tourDoneOf(TourKey::Humi, $this->session))->toBe(['humi.ask']);
});

it('leaves the center steps pending while the session has no center yet', function (): void {
    // Ended only to stand beside the open session of beforeEach: what matters is that it has no center yet.
    $preparing = DemoSession::query()->forceCreate(['tours' => ['nom035'], 'status' => DemoSessionStatus::Ended, 'started_at' => now()]);
    Employee::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);
    Campaign::factory()->closed()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $this->center->id]);

    foreach (resolve(TourCatalog::class)->for(TourKey::cases()) as $step) {
        $state = resolve(TourCatalog::class)->state($step, $preparing);

        if (in_array($step->tour, [TourKey::Base, TourKey::Nom035, TourKey::Attendance], true)) {
            expect($state)->toEqual(new StepState(false, null), $step->key);
        }
    }

    expect(array_map(fn (TourStep $step): string => $step->key, resolve(TourCatalog::class)->for([])))->toBe(['base.center', 'base.people']);
});

it('reads each fact of a tour once for all its steps', function (TourKey $tour): void {
    $this->actingAs(memberOf($this->demo, TenantRole::Owner));
    $queries = [];
    DB::listen(function (Illuminate\Database\Events\QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql.' '.json_encode($query->bindings);
    });

    $states = resolve(TourCatalog::class)->states([$tour], $this->session);

    expect(array_keys($states))->toBe(array_map(fn (TourStep $step): string => $step->key, resolve(TourCatalog::class)->for([$tour])))
        ->and($queries)->toBe(array_values(array_unique($queries)));
})->with(array_filter(TourKey::cases(), fn (TourKey $tour): bool => $tour !== TourKey::Base));

it('reads each fact once even when two tours share it', function (): void {
    $this->actingAs(memberOf($this->demo, TenantRole::Owner));
    $queries = [];
    DB::listen(function (Illuminate\Database\Events\QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql.' '.json_encode($query->bindings);
    });

    // Asistencia and Vacaciones both ask WorkerAccess what happened since the session started.
    resolve(TourCatalog::class)->states(TourKey::cases(), $this->session);

    expect($queries)->toBe(array_values(array_unique($queries)));
});
