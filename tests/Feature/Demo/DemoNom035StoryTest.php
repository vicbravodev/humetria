<?php

declare(strict_types=1);

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\InvitationStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\AreaRisk;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;

/** The NOM-035 evaluations of a demo center, by status, after preparing the demo. */
function demoEvaluation(string $centerKey, CampaignStatus $status): Campaign
{
    $center = WorkCenter::query()->where('name', DemoRoster::CENTERS[$centerKey])->firstOrFail();

    return Campaign::query()->where('work_center_id', $center->id)->where('kind', CampaignKind::Nom035Evaluation)->where('status', $status)->sole();
}

/** @return list<AreaRisk> */
function demoAreaGroups(Campaign $campaign): array
{
    return resolve(RiskResults::class)->areaGroups(resolve(CampaignRecords::class)->find($campaign->id) ?? throw new RuntimeException('No record.'));
}

beforeEach(function (): void {
    // In production the invitation jobs run later on the queue: the story must stand without them (and it is twice as fast).
    Bus::fake();
    resolve(DemoWorkspace::class)->prepare();
});

it('closes a first evaluation six months ago with a high-risk result in the plant night shift', function (): void {
    $closed = demoEvaluation('plant', CampaignStatus::Closed);
    $today = CarbonImmutable::now();

    expect($closed->closed_at)->not->toBeNull()
        ->and($closed->closed_at?->between($today->subDays(200), $today->subDays(160)))->toBeTrue();

    $groups = collect(demoAreaGroups($closed))->keyBy('name');
    // Alto or Muy alto is where most of the night shift's questionnaires fall, and nowhere else.
    $highMostly = $groups->filter(fn (AreaRisk $area): bool => ($area->final[3] + $area->final[4]) * 2 > $area->answered);

    expect($groups->get('Producción turno 2')?->answered)->toBeGreaterThanOrEqual(5)
        ->and($highMostly->keys()->all())->toBe(['Producción turno 2']);
});

it('applies Guía I to the 15 center, I and II to the 30, I and III to the 51 and 84', function (): void {
    $expected = [
        'monterrey' => ['nom035.guide-i'],
        'offices' => ['nom035.guide-i', 'nom035.guide-ii'],
        'distribution' => ['nom035.guide-i', 'nom035.guide-iii'],
        'plant' => ['nom035.guide-i', 'nom035.guide-iii'],
    ];

    foreach ($expected as $center => $guides) {
        foreach ([CampaignStatus::Closed, CampaignStatus::Active] as $status) {
            $campaign = demoEvaluation($center, $status);

            expect($campaign->instruments)->toEqualCanonicalizing($guides)
                ->and(Participant::query()->where('campaign_id', $campaign->id)->count())->toBe($campaign->workers);
        }
    }
});

it('shows no group below the minimum of 5', function (): void {
    foreach (['offices', 'distribution', 'plant'] as $center) {
        $closed = demoEvaluation($center, CampaignStatus::Closed);
        $record = resolve(CampaignRecords::class)->find($closed->id) ?? throw new RuntimeException('No record.');

        expect(resolve(RiskResults::class)->forCampaign($record)->state)->toBe(RiskBreakdown::AVAILABLE);

        foreach (demoAreaGroups($closed) as $area) {
            expect($area->answered)->toBeGreaterThanOrEqual(5);
        }
    }

    // The plant shows its shifts, quality and maintenance; the two directors stay hidden.
    expect(array_map(fn (AreaRisk $area): string => $area->name, demoAreaGroups(demoEvaluation('plant', CampaignStatus::Closed))))
        ->toContain('Producción turno 1', 'Producción turno 2');
});

it('has an intervention program with done, running, overdue and planned actions', function (): void {
    $program = Program::query()->where('campaign_id', demoEvaluation('plant', CampaignStatus::Closed)->id)->sole();
    $actions = ProgramAction::query()->where('program_id', $program->id)->get();
    $today = CarbonImmutable::now('America/Mexico_City')->startOfDay();

    $done = $actions->where('status', ActionStatus::Done);
    $running = $actions->filter(fn (ProgramAction $action): bool => $action->status === ActionStatus::InProgress && $action->due_on->gte($today));
    $overdue = $actions->filter(fn (ProgramAction $action): bool => $action->status !== ActionStatus::Done && $action->due_on->lt($today));
    $planned = $actions->filter(fn (ProgramAction $action): bool => $action->status === ActionStatus::Planned && $action->starts_on?->gt($today) === true);

    expect($done)->not->toBeEmpty()
        ->and($running)->not->toBeEmpty()
        ->and($overdue)->toHaveCount(1)
        ->and($planned)->not->toBeEmpty()
        ->and($done->first()?->completed_on?->lt($today))->toBeTrue();
});

it('keeps answers keyless', function (): void {
    $columns = Schema::getColumnListing('anonymous_responses');

    expect(preg_grep('/employee|participant|invitation|token|user|_at$/', $columns))->toBe([]);

    // Every Guía II and III answered went through the survey link, and each left one keyless row.
    $completed = ParticipantInstrument::query()->whereIn('instrument', ['nom035.guide-ii', 'nom035.guide-iii'])->where('status', InstrumentStatus::Completed)->count();

    expect($completed)->toBeGreaterThan(100)
        ->and(AnonymousResponse::query()->whereIn('instrument', ['nom035.guide-ii', 'nom035.guide-iii'])->count())->toBe($completed);
});

it('leaves an open campaign at about 60% with pending invitations reserved for the door', function (): void {
    $participants = collect(['monterrey', 'offices', 'distribution', 'plant'])
        ->flatMap(fn (string $center) => Participant::query()->where('campaign_id', demoEvaluation($center, CampaignStatus::Active)->id)->with('instruments')->get());
    $answered = $participants->filter(fn (Participant $participant): bool => $participant->instruments->every(fn (ParticipantInstrument $guide): bool => $guide->status === InstrumentStatus::Completed));
    $reserved = array_map(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key), array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->reservedForDoor));

    expect($participants)->toHaveCount(180)
        ->and($answered->count() / 180)->toBeGreaterThanOrEqual(0.55)->toBeLessThanOrEqual(0.65);

    foreach ($participants->whereIn('employee_id', $reserved) as $participant) {
        expect($participant->token_hash)->not->toBeNull()
            ->and($participant->invitation_status)->toBe(InvitationStatus::Sent)
            ->and($participant->instruments->pluck('status')->unique()->all())->toBe([InstrumentStatus::Pending]);
    }

    expect($participants->whereIn('employee_id', $reserved))->toHaveCount(count($reserved));
});
