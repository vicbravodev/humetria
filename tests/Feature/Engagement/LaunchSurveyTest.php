<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Engagement\Actions\LaunchSurvey;
use App\Modules\Engagement\Actions\QueueInvitations;
use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Exceptions\SurveyCannotLaunch;
use App\Modules\Engagement\Exceptions\SurveyStateChanged;
use App\Modules\Engagement\Jobs\SendInvitations;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

mutates(LaunchSurvey::class, QueueInvitations::class, SurveyCannotLaunch::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    Bus::fake();
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->survey = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * @param  array<string, mixed>  $attributes
 * @return Illuminate\Database\Eloquent\Collection<int, Employee>
 */
function launchStaff(Tenant $tenant, WorkCenter $center, int $count, array $attributes = []): Illuminate\Database\Eloquent\Collection
{
    return Employee::factory()->count($count)->create([
        'tenant_id' => $tenant->id,
        'work_center_id' => $center->id,
        'area_id' => Area::factory()->create(['tenant_id' => $tenant->id])->id,
        ...$attributes,
    ]);
}

function launchOn(string $surveyId, string $closesOn, int $userId): Survey
{
    return resolve(LaunchSurvey::class)->handle($surveyId, new CarbonImmutable($closesOn), $userId);
}

it('refuses to launch with fewer than five people and leaves nothing behind', function (): void {
    launchStaff($this->tenant, $this->plant, 4);

    expect(fn () => launchOn($this->survey->id, '2026-11-20', $this->user->id))
        ->toThrow(SurveyCannotLaunch::class, 'Se necesitan al menos 5 personas para proteger el anonimato');

    expect(Invitation::query()->count())->toBe(0)
        ->and($this->survey->fresh()?->status)->toBe(SurveyStatus::Draft);
    Bus::assertNothingBatched();
});

it('launches with exactly five people', function (): void {
    launchStaff($this->tenant, $this->plant, 5);

    $survey = launchOn($this->survey->id, '2026-11-20', $this->user->id);

    expect($survey->status)->toBe(SurveyStatus::Active)
        ->and($survey->invited)->toBe(5)
        ->and($survey->launched_at?->toIso8601String())->toBe('2026-11-09T15:00:00+00:00')
        ->and($survey->closes_on?->toDateString())->toBe('2026-11-20')
        ->and(Invitation::query()->count())->toBe(5);
});

it('queues one job per block of fifty people with a link, and marks the others as without email', function (): void {
    launchStaff($this->tenant, $this->plant, 120);
    $withoutEmail = launchStaff($this->tenant, $this->plant, 1, ['email' => null])->sole();

    launchOn($this->survey->id, '2026-11-20', $this->user->id);

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 3 && $batch->jobs->every(fn (mixed $job): bool => $job instanceof SendInvitations));
    $statuses = Invitation::query()->get()->groupBy(fn (Invitation $i): string => $i->invitation_status->value ?? '')->map->count()->all();
    expect($statuses)->toBe(['queued' => 120, 'no_email' => 1])
        ->and(Invitation::query()->where('employee_id', $withoutEmail->id)->sole()->invitation_status)->toBe(InvitationStatus::NoEmail)
        ->and(Invitation::query()->whereNotNull('invitation_batch')->distinct()->count('invitation_batch'))->toBe(1);
});

it('accepts a deadline from tomorrow to sixty days, in the local calendar', function (string $closesOn, bool $accepted): void {
    launchStaff($this->tenant, $this->plant, 5);

    $attempt = fn () => launchOn($this->survey->id, $closesOn, $this->user->id);

    $accepted ? expect($attempt()->status)->toBe(SurveyStatus::Active) : expect($attempt)->toThrow(SurveyCannotLaunch::class);
})->with([
    'today' => ['2026-11-09', false],
    'yesterday' => ['2026-11-08', false],
    'tomorrow' => ['2026-11-10', true],
    'sixty days' => ['2027-01-08', true],
    'sixty one days' => ['2027-01-09', false],
]);

it('counts today by the local calendar, not by the UTC one', function (): void {
    Carbon::setTestNow('2026-11-10 03:00:00');
    launchStaff($this->tenant, $this->plant, 5);

    expect(SurveyClock::day()->toDateString())->toBe('2026-11-09')
        ->and(launchOn($this->survey->id, '2026-11-10', $this->user->id)->status)->toBe(SurveyStatus::Active);
});

it('says the last day has passed only once it is over in the local calendar', function (string $now, bool $passed): void {
    Carbon::setTestNow($now);

    expect(SurveyClock::hasPassed(new CarbonImmutable('2026-11-09')))->toBe($passed);
})->with([
    'that day, midday' => ['2026-11-09 18:00:00', false],
    'the last second of it' => ['2026-11-10 05:59:59', false],
    'the first second after it' => ['2026-11-10 06:00:00', true],
]);

it('does not launch twice, and the second try duplicates nothing', function (): void {
    launchStaff($this->tenant, $this->plant, 5);
    launchOn($this->survey->id, '2026-11-20', $this->user->id);

    expect(fn () => launchOn($this->survey->id, '2026-11-20', $this->user->id))->toThrow(SurveyStateChanged::class)
        ->and(Invitation::query()->count())->toBe(5);
    Bus::assertBatchCount(1);
});

it('records the launch in the audit trail and the log with counts, never names', function (): void {
    launchStaff($this->tenant, $this->plant, 5);
    Log::spy();

    launchOn($this->survey->id, '2026-11-20', $this->user->id);

    $entry = AuditEntry::query()->where('event', 'engagement.survey_launched')->sole();
    expect($entry->subject_id)->toBe($this->survey->id)
        ->and($entry->tenant_id)->toBe($this->tenant->id)
        ->and($entry->metadata)->toEqual(['invited' => 5]);
    Log::shouldHaveReceived('info')->with('engagement.survey_launched', ['survey_id' => $this->survey->id, 'invited' => 5])->once();
});
