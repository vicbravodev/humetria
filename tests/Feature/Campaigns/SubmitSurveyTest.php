<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\SubmitSurvey;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Http\Controllers\SurveyController;
use App\Modules\Campaigns\Http\Requests\SubmitSurveyRequest;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Responses\Support\DatabaseResponseRecorder;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

mutates(SubmitSurvey::class, SurveyController::class, SubmitSurveyRequest::class, DatabaseResponseRecorder::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-05 15:00:00');
    $this->participant = surveyTaker(['nom035.guide-i', 'nom035.guide-iii']);
});

/**
 * A person of an active campaign with Acme whose link is /survey/the-token, given the guides listed.
 *
 * @param  list<string>  $instruments
 */
function surveyTaker(array $instruments): Participant
{
    $campaign = Campaign::factory()->active('2026-11-14')->create([
        'tenant_id' => Tenant::factory()->create(['trade_name' => 'Acme'])->id,
        'instruments' => $instruments,
    ]);
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id, 'token_hash' => Participant::hashToken('the-token')]);

    foreach ($instruments as $instrument) {
        ParticipantInstrument::factory()->create(['participant_id' => $participant->id, 'instrument' => $instrument]);
    }

    return $participant;
}

function submitGuide(string $instrument, array $answers, string $token = 'the-token'): Illuminate\Testing\TestResponse
{
    return test()->post(route('survey.submit', [$token, $instrument]), ['answers' => $answers]);
}

function guideStatus(Participant $participant, string $instrument): InstrumentStatus
{
    return ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $participant->id)->where('instrument', $instrument)->sole()->status;
}

it('lives at /survey/{token}/{instrument}', function (): void {
    expect(route('survey.submit', ['abc', 'nom035.guide-iii'], absolute: false))->toBe('/survey/abc/nom035.guide-iii');
});

it('stores a Guía III anonymously, under the campaign\'s work center and the person\'s current area', function (): void {
    $employee = Employee::query()->withoutGlobalScopes()->findOrFail($this->participant->employee_id);
    $newArea = Area::factory()->create(['tenant_id' => $employee->tenant_id]);
    $employee->update(['area_id' => $newArea->id]);
    $answers = nom035Answers('nom035.guide-iii', 'almost_never', clients: false);

    submitGuide('nom035.guide-iii', $answers)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('survey.show', 'the-token'));

    $response = AnonymousResponse::query()->withoutGlobalScopes()->sole();
    $campaign = Campaign::query()->withoutGlobalScopes()->findOrFail($this->participant->campaign_id);

    expect($response->only(['tenant_id', 'campaign_id', 'work_center_id', 'area_id', 'instrument', 'instrument_version']))->toBe([
        'tenant_id' => $campaign->tenant_id,
        'campaign_id' => $campaign->id,
        'work_center_id' => $campaign->work_center_id,
        'area_id' => $newArea->id,
        'instrument' => 'nom035.guide-iii',
        'instrument_version' => 'dof-2018-10-23',
    ])
        ->and($response->answers)->toEqual($answers)
        ->and(guideStatus($this->participant, 'nom035.guide-iii'))->toBe(InstrumentStatus::Completed)
        ->and(guideStatus($this->participant, 'nom035.guide-i'))->toBe(InstrumentStatus::Pending)
        ->and(TraumaScreening::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('stores a Guía I with the person and whether they need a clinical assessment', function (array $yes, bool $assessment): void {
    submitGuide('nom035.guide-i', guideOneAnswers($yes))->assertSessionHasNoErrors();

    $screening = TraumaScreening::query()->withoutGlobalScopes()->sole();

    expect($screening->employee_id)->toBe($this->participant->employee_id)
        ->and($screening->campaign_id)->toBe($this->participant->campaign_id)
        ->and($screening->requires_clinical_assessment)->toBe($assessment)
        ->and($screening->answered_at->toIso8601String())->toBe('2026-11-05T15:00:00+00:00')
        ->and($screening->answers)->toEqual(guideOneAnswers($yes))
        ->and(AnonymousResponse::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(guideStatus($this->participant, 'nom035.guide-i'))->toBe(InstrumentStatus::Completed);
})->with([
    'no event' => [[], false],
    'an event with two section IV answers' => [[3, 16, 17], true],
]);

it('takes the guides in any order: the anonymous one does not wait for the named one', function (): void {
    submitGuide('nom035.guide-iii', nom035Answers('nom035.guide-iii'))->assertSessionHasNoErrors();
    submitGuide('nom035.guide-i', guideOneAnswers())->assertSessionHasNoErrors();

    expect(guideStatus($this->participant, 'nom035.guide-iii'))->toBe(InstrumentStatus::Completed)
        ->and(guideStatus($this->participant, 'nom035.guide-i'))->toBe(InstrumentStatus::Completed);

    $this->get(route('survey.show', 'the-token'))->assertInertia(fn ($page) => $page->where('unavailable', 'done'));
});

it('stores nothing from an incomplete or incoherent guide, and says why', function (array $answers): void {
    submitGuide('nom035.guide-iii', $answers)->assertSessionHasErrors('answers');

    expect(AnonymousResponse::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(guideStatus($this->participant, 'nom035.guide-iii'))->toBe(InstrumentStatus::Pending);
})->with([
    'an item missing' => [Illuminate\Support\Arr::except(nom035Answers('nom035.guide-iii'), '30')],
    'a no with its block answered' => [array_replace(nom035Answers('nom035.guide-iii'), ['supervises' => 'no'])],
    'Guía II answers' => [nom035Answers('nom035.guide-ii')],
    'nothing' => [[]],
]);

it('takes each guide once: a second submission stores nothing', function (): void {
    submitGuide('nom035.guide-iii', nom035Answers('nom035.guide-iii'))->assertSessionHasNoErrors();
    submitGuide('nom035.guide-iii', nom035Answers('nom035.guide-iii', 'never'))->assertRedirect(route('survey.show', 'the-token'));

    expect(AnonymousResponse::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(AnonymousResponse::query()->withoutGlobalScopes()->sole()->answers['1'])->toBe('sometimes');
});

it('refuses a guide the person was not given, or one cancelled', function (): void {
    submitGuide('nom035.guide-ii', nom035Answers('nom035.guide-ii'));
    ParticipantInstrument::query()->withoutGlobalScopes()->where('instrument', 'nom035.guide-iii')->update(['status' => InstrumentStatus::Cancelled, 'cancelled_at' => now()]);
    submitGuide('nom035.guide-iii', nom035Answers('nom035.guide-iii'));

    expect(AnonymousResponse::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('refuses answers after the last day, after closing, from a wrong link or while the company is suspended', function (Closure $change, string $token): void {
    $change($this->participant);

    submitGuide('nom035.guide-iii', nom035Answers('nom035.guide-iii'), $token)->assertRedirect(route('survey.show', $token));

    expect(AnonymousResponse::query()->withoutGlobalScopes()->count())->toBe(0);
})->with([
    'past the last day' => [fn () => Carbon::setTestNow('2026-11-15 06:00:00'), 'the-token'],
    'closed' => [fn (Participant $participant) => Campaign::query()->withoutGlobalScopes()->whereKey($participant->campaign_id)->update(['status' => 'closed', 'closed_at' => now()]), 'the-token'],
    'a wrong link' => [fn () => null, 'another-token'],
    'suspended' => [fn (Participant $participant) => Tenant::query()->whereKey($participant->tenant_id)->update(['deactivated_at' => now()]), 'the-token'],
]);

it('never writes an answer, nor the id of an anonymous response, to the log', function (): void {
    $lines = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$lines): void {
        $lines[] = $event->message.' '.json_encode($event->context);
    });

    submitGuide('nom035.guide-iii', nom035Answers('nom035.guide-iii', 'almost_never'))->assertSessionHasNoErrors();
    submitGuide('nom035.guide-i', guideOneAnswers([2, 7]))->assertSessionHasNoErrors();

    $responseId = AnonymousResponse::query()->withoutGlobalScopes()->sole()->id;
    $log = implode("\n", $lines);

    expect($log)->toContain('response.recorded')
        ->toContain('campaign_participant.completed')
        ->not->toContain('almost_never')
        ->not->toContain('"yes"')
        ->not->toContain($responseId)
        ->not->toContain('requires_clinical_assessment');
});

it('limits how often one link sends answers, without blocking a room of people on one network', function (): void {
    foreach (range(1, 10) as $attempt) {
        submitGuide('nom035.guide-iii', [], 'the-token');
    }

    submitGuide('nom035.guide-iii', [], 'the-token')->assertTooManyRequests();

    foreach (range(1, 60) as $person) {
        submitGuide('nom035.guide-iii', [], "link-{$person}")->assertRedirect();
    }
});

it('sends a submission over the limit back to the survey, saying so with "usted"', function (): void {
    $survey = route('survey.show', 'the-token');

    foreach (range(1, 10) as $attempt) {
        test()->from($survey)->post(route('survey.submit', ['the-token', 'nom035.guide-iii']), ['answers' => []], ['X-Inertia' => 'true']);
    }

    test()->from($survey)->post(route('survey.submit', ['the-token', 'nom035.guide-iii']), ['answers' => []], ['X-Inertia' => 'true'])
        ->assertRedirect($survey)
        ->assertSessionHas(Inertia\Support\SessionKey::FLASH_DATA, ['toast' => ['type' => 'error', 'message' => 'Demasiados intentos. Espere un minuto y vuelva a intentarlo.']]);
});

it('sends a submission whose page expired back to the survey, its answers still on the device', function (bool $inertia): void {
    // CSRF is only verified outside unit tests.
    app()->detectEnvironment(fn (): string => 'local');

    $this->post(route('survey.submit', ['the-token', 'nom035.guide-iii']), ['answers' => nom035Answers('nom035.guide-iii')], $inertia ? ['X-Inertia' => 'true'] : [])
        ->assertRedirect(route('survey.show', 'the-token'))
        ->assertSessionHas(Inertia\Support\SessionKey::FLASH_DATA, ['toast' => [
            'type' => 'info',
            'message' => 'La página se actualizó por seguridad. Sus respuestas siguen guardadas en este dispositivo: envíelas de nuevo.',
        ]]);

    expect(guideStatus($this->participant, 'nom035.guide-iii'))->toBe(InstrumentStatus::Pending)
        ->and(AnonymousResponse::query()->withoutGlobalScopes()->count())->toBe(0);
})->with(['inertia' => true, 'plain' => false]);

it('keeps the panel\'s expired page for a form outside the survey', function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    config(['app.debug' => false]);

    $this->post(route('login'), ['email' => 'ana@acme.mx', 'password' => 'secreto'])
        ->assertStatus(419)
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page->component('error')->where('status', 419));
});

it('locks the person and reads the campaign under a shared lock before taking a guide', function (): void {
    $queries = [];
    Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    submitGuide('nom035.guide-i', guideOneAnswers())->assertSessionHasNoErrors();

    // A second submission of the same guide waits on the person's row and then finds it answered; a close waits on
    // the campaign's row, so nothing is taken after it closes.
    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'from "campaign_participants"') && str_ends_with($sql, 'for update')))->toBeTrue()
        ->and(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'from "campaigns"') && str_ends_with($sql, 'for share')))->toBeTrue();
});

it('never keeps the answers of a malformed submission in the session', function (): void {
    submitGuide('nom035.guide-iii', ['1' => ['sometimes'], '2' => 'almost_never'])->assertSessionHasErrors('answers.1');

    expect(session()->getOldInput('answers'))->toBeNull();
});
