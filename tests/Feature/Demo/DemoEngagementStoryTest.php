<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Engagement\Enums\Direction;
use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Pulse\TrendPage;
use App\Modules\Engagement\Support\Results\Trend;
use App\Modules\Engagement\Support\Thresholds;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // In production the invitation jobs run later on the queue: the story must stand without them.
    Bus::fake();
    resolve(DemoWorkspace::class)->prepare();
});

it('has a closed climate survey and two closed pulses whose trend improves', function (): void {
    $closed = Survey::query()->where('status', SurveyStatus::Closed)->orderBy('closed_at')->get();

    expect($closed->map(fn (Survey $survey): SurveyKind => $survey->kind)->all())->toBe([SurveyKind::Climate, SurveyKind::Pulse, SurveyKind::Pulse]);

    // The engagement index the trend page draws: up wave after wave, with an arrow each time.
    $points = Trend::index(resolve(TrendPage::class)->waves(), resolve(Thresholds::class));

    expect($points)->toHaveCount(3)
        ->and($points[1]->value)->toBeGreaterThan($points[0]->value)
        ->and($points[2]->value)->toBeGreaterThan($points[1]->value)
        ->and([$points[1]->direction, $points[2]->direction])->toBe([Direction::Up, Direction::Up]);

    // Nothing launches by itself and changes the story: the demo has no pulse calendar.
    expect(PulseSchedule::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('has one open pulse at about 60% with at least 40 pending invitations', function (): void {
    $open = Survey::query()->where('status', SurveyStatus::Active)->sole();
    $invitations = Invitation::query()->where('survey_id', $open->id)->get();
    $answered = $invitations->where('answered', true);
    $reserved = array_map(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key), array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->reservedForDoor));

    expect($open->kind)->toBe(SurveyKind::Pulse)
        ->and($open->closes_on?->isFuture())->toBeTrue()
        ->and($invitations)->toHaveCount(180)
        ->and($answered->count() / 180)->toBeGreaterThanOrEqual(0.55)->toBeLessThanOrEqual(0.65)
        ->and($invitations->where('answered', false)->count())->toBeGreaterThanOrEqual(40)
        ->and(Response::query()->where('survey_id', $open->id)->count())->toBe($answered->count());

    // The door's people hold a working link, still unanswered.
    $door = $invitations->whereIn('employee_id', $reserved);

    expect($door)->toHaveCount(count($reserved));

    foreach ($door as $invitation) {
        expect($invitation->answered)->toBeFalse()
            ->and($invitation->cancelled)->toBeFalse()
            ->and($invitation->token_hash)->toBe(Invitation::hashToken($invitation->surveyToken()))
            ->and($invitation->invitation_status)->toBe(InvitationStatus::Sent);
    }
});

it('keeps every group at or above 5 respondents', function (): void {
    $surveys = Survey::query()->get();

    expect($surveys)->toHaveCount(4);

    // Results cut by one attribute at a time: in every survey, each group that has answers has at least 5.
    foreach ($surveys as $survey) {
        foreach (['work_center_id', 'area_id', 'tenure_band', 'manager_group_id'] as $attribute) {
            $groups = Response::query()->where('survey_id', $survey->id)->whereNotNull($attribute)->pluck($attribute)->countBy();

            expect($groups)->not->toBeEmpty();

            foreach ($groups as $respondents) {
                expect($respondents)->toBeGreaterThanOrEqual(5);
            }
        }
    }
});

it('keeps answers keyless', function (): void {
    expect(preg_grep('/employee|participant|invitation|token|user|_at$/', Schema::getColumnListing('engagement_responses')))->toBe([]);

    // Every answer went through the person's link and left one keyless row; the invitation only knows it was answered.
    $answered = Invitation::query()->where('answered', true)->count();

    expect($answered)->toBeGreaterThan(400)
        ->and(Response::query()->count())->toBe($answered);
});
