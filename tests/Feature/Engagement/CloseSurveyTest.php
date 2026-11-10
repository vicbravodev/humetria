<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Engagement\Actions\CloseExpiredSurveys;
use App\Modules\Engagement\Actions\CloseSurvey;
use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Events\SurveyClosed;
use App\Modules\Engagement\Exceptions\SurveyStateChanged;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

mutates(CloseSurvey::class, CloseExpiredSurveys::class, SurveyClosed::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

it('closes an active survey, stamps it, announces it once and writes the audit trail without any answer', function (): void {
    Event::fake([SurveyClosed::class]);
    Log::spy();
    $survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);

    $closed = resolve(CloseSurvey::class)->handle($survey->id, 7);

    expect($closed->status)->toBe(SurveyStatus::Closed)
        ->and($closed->closed_at?->toDateTimeString())->toBe('2026-11-09 15:00:00')
        ->and($survey->refresh()->status)->toBe(SurveyStatus::Closed);
    Event::assertDispatched(SurveyClosed::class, fn (SurveyClosed $event): bool => $event->surveyId === $survey->id && $event->kind === SurveyKind::Climate);
    Event::assertDispatchedTimes(SurveyClosed::class, 1);
    Log::shouldHaveReceived('info')->with('engagement.survey_closed', ['survey_id' => $survey->id, 'automatic' => false])->once();
    expect(AuditEntry::query()->where('event', 'engagement.survey_closed')->sole()->subject_id)->toBe($survey->id);
});

it('stores no priorities while nobody has answered', function (): void {
    $survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);

    expect(resolve(CloseSurvey::class)->handle($survey->id)->priorities)->toBe([]);
});

it('stores, on closing, the drivers to act on first, at company level and with no figure', function (): void {
    // Everyone answers the engagement questions with their level (1 to 5); recognition follows it and comes out low, the
    // rest answer 4 whatever they feel.
    $survey = climateResultsSurvey($this->tenant, 40, function (int $i): array {
        $level = 1 + $i % 5;
        $answers = ['eng.pride' => $level, 'eng.motivation' => $level, 'eng.connection' => $level, 'eng.recommend' => 9, 'eng.stay' => 4];
        foreach (ItemBank::closed() as $item) {
            $answers[$item->key] ??= $item->driver === Driver::Recognition ? $level : 4;
        }

        return $answers;
    }, closed: false);

    expect(resolve(CloseSurvey::class)->handle($survey->id)->priorities)->toEqual([['driver' => 'recognition', 'level' => 'high']]);
    expect($survey->refresh()->priorities)->toEqual([['driver' => 'recognition', 'level' => 'high']]);
});

it('does not close twice nor announce twice', function (): void {
    Event::fake([SurveyClosed::class]);
    $survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);
    resolve(CloseSurvey::class)->handle($survey->id);

    expect(fn () => resolve(CloseSurvey::class)->handle($survey->id))->toThrow(SurveyStateChanged::class);
    Event::assertDispatchedTimes(SurveyClosed::class, 1);
});

it('does not close a draft', function (): void {
    $survey = Survey::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => resolve(CloseSurvey::class)->handle($survey->id))->toThrow(SurveyStateChanged::class);
    expect($survey->refresh()->status)->toBe(SurveyStatus::Draft);
});

it('closes, in every company, the surveys whose last day ended yesterday in the display time zone, and not the one ending today', function (): void {
    Event::fake([SurveyClosed::class]);
    $second = Tenant::factory()->create();
    $third = Tenant::factory()->create();
    $yesterday = Survey::factory()->active('2026-11-08')->create(['tenant_id' => $this->tenant->id]);
    $today = Survey::factory()->active('2026-11-09')->create(['tenant_id' => $second->id]);
    $elsewhere = Survey::factory()->active('2026-11-01')->create(['tenant_id' => $third->id]);

    expect(resolve(CloseExpiredSurveys::class)->handle())->toBe(2);

    workIn($this->tenant);
    expect($yesterday->refresh()->status)->toBe(SurveyStatus::Closed);
    workIn($second);
    expect($today->refresh()->status)->toBe(SurveyStatus::Active);
    workIn($third);
    expect($elsewhere->refresh()->status)->toBe(SurveyStatus::Closed);
    Event::assertDispatchedTimes(SurveyClosed::class, 2);
});

it('logs an expiry as automatic', function (): void {
    Log::spy();
    $survey = Survey::factory()->active('2026-11-01')->create(['tenant_id' => $this->tenant->id]);

    resolve(CloseExpiredSurveys::class)->handle();

    Log::shouldHaveReceived('info')->with('engagement.survey_closed', ['survey_id' => $survey->id, 'automatic' => true])->once();
});
