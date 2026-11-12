<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Engagement\Actions\LaunchScheduledPulses;
use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(LaunchScheduledPulses::class);

beforeEach(function (): void {
    // 12:00 in Mexico City on the 15th: the schedules of the 15th are due.
    $this->travelTo('2026-11-15 18:00:00');
    Bus::fake();
});

/** A company with `$people` people and a schedule that is due `$daysAgo` days ago. */
function pulseDueCompany(int $people = 6, array $schedule = [], int $daysAgo = 0): Tenant
{
    $tenant = Tenant::factory()->create();
    $plant = WorkCenter::factory()->for($tenant)->create();
    $area = Area::factory()->create(['tenant_id' => $tenant->id]);
    Employee::factory()->count($people)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area->id]);
    PulseSchedule::factory()->create(['tenant_id' => $tenant->id, 'next_run_on' => now()->subDays($daysAgo)->toDateString(), 'created_by' => User::factory()->create()->id, ...$schedule]);

    return $tenant;
}

function pulseLaunchRun(): int
{
    return resolve(LaunchScheduledPulses::class)->handle();
}

function pulseOf(Tenant $tenant): ?Survey
{
    return Survey::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('kind', SurveyKind::Pulse)->first();
}

function pulseScheduleOf(Tenant $tenant): PulseSchedule
{
    return PulseSchedule::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
}

it('launches the pulse on its day, with the invitations queued, and moves the date a month ahead', function (): void {
    $tenant = pulseDueCompany();

    expect(pulseLaunchRun())->toBe(1);

    $pulse = pulseOf($tenant);
    expect($pulse?->status)->toBe(SurveyStatus::Active)
        ->and($pulse?->closes_on?->toDateString())->toBe('2026-11-22')
        ->and($pulse?->invited)->toBe(6)
        ->and(Invitation::query()->withoutGlobalScopes()->where('survey_id', $pulse?->id)->count())->toBe(6)
        ->and(pulseScheduleOf($tenant)->next_run_on->toDateString())->toBe('2026-12-15')
        ->and(pulseScheduleOf($tenant)->created_by)->toBe($pulse?->created_by);
    Bus::assertBatched(fn () => true);
});

it('moves a quarterly schedule to the close of the next quarter', function (): void {
    $this->travelTo('2026-12-01 18:00:00');
    $tenant = pulseDueCompany(schedule: ['frequency' => PulseFrequency::Quarterly, 'day_of_month' => 1]);

    pulseLaunchRun();

    expect(pulseScheduleOf($tenant)->next_run_on->toDateString())->toBe('2027-03-01');
});

it('keeps open the days the company chose', function (): void {
    $tenant = pulseDueCompany(schedule: ['open_days' => 14]);

    pulseLaunchRun();

    expect(pulseOf($tenant)?->closes_on?->toDateString())->toBe('2026-11-29');
});

it('does not launch a second pulse when the command runs again the same day', function (): void {
    $tenant = pulseDueCompany();

    pulseLaunchRun();
    expect(pulseLaunchRun())->toBe(0);

    expect(Survey::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('kind', SurveyKind::Pulse)->count())->toBe(1);
});

it('launches one pulse after days without running and puts the next date in the future', function (): void {
    $tenant = pulseDueCompany(daysAgo: 5);

    expect(pulseLaunchRun())->toBe(1);

    expect(Survey::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('kind', SurveyKind::Pulse)->count())->toBe(1)
        ->and(pulseScheduleOf($tenant)->next_run_on->toDateString())->toBe('2026-12-15');
});

it('waits for its day and for a schedule that is not paused', function (): void {
    $paused = pulseDueCompany(schedule: ['active' => false]);
    $tomorrow = pulseDueCompany();
    pulseScheduleOf($tomorrow)->update(['next_run_on' => '2026-11-16']);

    expect(pulseLaunchRun())->toBe(0)
        ->and(pulseOf($paused))->toBeNull()
        ->and(pulseOf($tomorrow))->toBeNull()
        ->and(pulseScheduleOf($paused)->next_run_on->toDateString())->toBe('2026-11-15');
});

it('decides the day by the display time zone, not by UTC', function (): void {
    // 2026-11-16 03:00 UTC is still the 15th at 21:00 in Mexico City.
    $this->travelTo('2026-11-16 03:00:00');
    $tenant = pulseDueCompany();
    pulseScheduleOf($tenant)->update(['next_run_on' => '2026-11-15']);

    expect(pulseLaunchRun())->toBe(1);

    $this->travelTo('2026-11-16 03:00:00');
    $other = pulseDueCompany();
    pulseScheduleOf($other)->update(['next_run_on' => '2026-11-16']);

    expect(pulseLaunchRun())->toBe(0);
});

it('skips the cycle and moves on when the company cannot take it', function (string $reason, Closure $arrange): void {
    Log::spy();
    $tenant = pulseDueCompany();
    $arrange($tenant);

    expect(pulseLaunchRun())->toBe(0);

    expect(Survey::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('kind', SurveyKind::Pulse)->count())->toBe($reason === 'pulse_open' ? 1 : 0)
        ->and(pulseScheduleOf($tenant)->next_run_on->toDateString())->toBe('2026-12-15');
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'engagement.pulse_skipped' && $context['reason'] === $reason)->once();
})->with([
    'a climate survey in course' => ['climate_active', fn (Tenant $tenant) => Survey::factory()->active()->create(['tenant_id' => $tenant->id])],
    'a pulse in course' => ['pulse_open', fn (Tenant $tenant) => Survey::factory()->pulse()->active()->create(['tenant_id' => $tenant->id])],
    'a pulse draft' => ['pulse_open', fn (Tenant $tenant) => Survey::factory()->pulse()->create(['tenant_id' => $tenant->id])],
    'a suspended company' => ['company_blocked', fn (Tenant $tenant) => DB::table('tenants')->where('id', $tenant->id)->update(['restriction' => 'suspended', 'restriction_reason' => 'Prueba', 'restricted_at' => now()])],
]);

it('leaves no survey and no draft in a company of four people', function (): void {
    Log::spy();
    $tenant = pulseDueCompany(people: 4);

    expect(pulseLaunchRun())->toBe(0);

    expect(pulseOf($tenant))->toBeNull()
        ->and(pulseScheduleOf($tenant)->next_run_on->toDateString())->toBe('2026-12-15');
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'engagement.pulse_skipped' && $context['reason'] === 'too_few_people')->once();
    Bus::assertNothingBatched();
});

it('launches with five people', function (): void {
    $tenant = pulseDueCompany(people: 5);

    expect(pulseLaunchRun())->toBe(1)->and(pulseOf($tenant)?->invited)->toBe(5);
});

it('follows the calendar of modes and honors the mode RH chose, once', function (): void {
    $tenant = pulseDueCompany();
    Survey::factory()->pulse()->closed()->count(2)->create(['tenant_id' => $tenant->id, 'pulse_mode' => PulseMode::Tour]);
    DB::table('engagement_surveys')->where('tenant_id', $tenant->id)->update(['launched_at' => now()->subMonths(2)]);

    pulseLaunchRun();
    expect(Survey::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', SurveyStatus::Active)->sole()->pulse_mode)->toBe(PulseMode::Manager);

    $chosen = pulseDueCompany(schedule: ['next_mode' => PulseMode::Manager]);
    pulseLaunchRun();

    expect(pulseOf($chosen)?->pulse_mode)->toBe(PulseMode::Manager)
        ->and(pulseScheduleOf($chosen)->next_mode)->toBeNull();
});

it('starts with a tour when the company has no priorities to follow', function (): void {
    $tenant = pulseDueCompany();

    pulseLaunchRun();

    expect(pulseOf($tenant)?->pulse_mode)->toBe(PulseMode::Tour);
});

it('clears the heads-up mark when the cycle is done', function (): void {
    $tenant = pulseDueCompany(schedule: ['announced_for' => '2026-11-15']);

    pulseLaunchRun();

    expect(pulseScheduleOf($tenant)->announced_for)->toBeNull();
});

it('does not let one company stop the others', function (): void {
    Log::spy();
    // Its next pulse is a follow-up and there is nothing to follow: this company fails, the other launches.
    $failing = pulseDueCompany();
    DB::table('engagement_pulse_schedules')->where('tenant_id', $failing->id)->update(['next_mode' => 'follow_up']);
    $healthy = pulseDueCompany();

    expect(pulseLaunchRun())->toBe(1);

    expect(pulseOf($failing))->toBeNull()
        ->and(pulseOf($healthy)?->status)->toBe(SurveyStatus::Active);
    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'engagement.pulse_launch_failed' && $context['exception'] === InvalidArgumentException::class)->once();
});

it('runs from the command and reports how many it launched', function (): void {
    pulseDueCompany();

    $this->artisan('engagement:launch-pulses')->expectsOutput('Pulsos lanzados: 1')->assertSuccessful();
});

it('writes the log of the launch without any answer or person', function (): void {
    Log::spy();
    pulseDueCompany();

    pulseLaunchRun();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'engagement.pulse_launched' && array_keys($context) === ['survey_id', 'mode', 'invited'])->once();
});
