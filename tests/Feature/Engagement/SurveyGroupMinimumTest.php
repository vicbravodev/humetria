<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Engagement\Actions\LaunchSurvey;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Exceptions\SurveyCannotLaunch;
use App\Modules\Engagement\Jobs\SendInvitations;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SurveyInvitation;
use App\Modules\Engagement\Support\Thresholds;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Survey::class, LaunchSurvey::class, SurveyCannotLaunch::class, SurveyInvitation::class, Thresholds::class);

// The minimum of people per group of a climate or pulse survey is the company's (1 to 50), frozen when it launches: the
// invitation promised that number to whoever answers, so a later change of the company's never reaches it.

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    Notification::fake();
    RateLimiter::clear('survey-invitations');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']);
    $this->user = User::factory()->create();
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
});

/** The company now asks for `$minimum` people per group, as the next request reads it. */
function climateCompanyMinimum(Tenant $tenant, ?int $minimum): void
{
    $tenant->forceFill(['min_group' => $minimum])->save();
    app()->forgetScopedInstances();
    workIn($tenant);
}

/** Launches a climate survey to `$people` people of the plant, its links still in the queue. */
function climateLaunchedTo(mixed $test, int $people): Survey
{
    Employee::factory()->count($people)->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->plant->id, 'area_id' => $test->area->id]);
    $survey = Survey::factory()->create(['tenant_id' => $test->tenant->id]);
    Bus::fake();

    return resolve(LaunchSurvey::class)->handle($survey->id, new CarbonImmutable('2026-11-20'), $test->user->id);
}

/** Sends the links the launch queued and gives the html of one email. */
function climateInvitationHtml(): string
{
    Bus::assertBatched(function (PendingBatch $batch): bool {
        $batch->jobs->each(fn (SendInvitations $job) => $job->handle());

        return true;
    });
    $html = null;
    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $mail, array $channels, AnonymousNotifiable $notifiable) use (&$html): bool {
        $html ??= (string) $mail->toMail($notifiable)->render();

        return true;
    });

    return $html ?? throw new RuntimeException('No email was sent.');
}

it('freezes the company minimum when a survey launches, and a later change does not reach it', function (int $minimum): void {
    climateCompanyMinimum($this->tenant, $minimum);

    $survey = climateLaunchedTo($this, 12);
    climateCompanyMinimum($this->tenant, 7);

    expect($survey->fresh()?->min_group)->toBe($minimum)
        ->and($survey->fresh()?->groupMinimum())->toBe($minimum);
})->with([3, 1, 12]);

it('freezes the platform default for a company that never chose one', function (): void {
    config(['results.min_group' => 6]);

    expect(climateLaunchedTo($this, 6)->fresh()?->min_group)->toBe(6);
});

it('lets a draft follow the company minimum until it launches', function (): void {
    $draft = Survey::factory()->create(['tenant_id' => $this->tenant->id]);

    climateCompanyMinimum($this->tenant, 3);
    expect($draft->min_group)->toBeNull()->and($draft->groupMinimum())->toBe(3);

    climateCompanyMinimum($this->tenant, 12);
    expect($draft->groupMinimum())->toBe(12);
});

it('keeps the platform default for a survey launched before surveys kept their minimum', function (): void {
    $survey = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
    climateCompanyMinimum($this->tenant, 3);

    expect($survey->groupMinimum())->toBe(5);
});

it('refuses a launch one person short of the company minimum and launches with exactly the minimum', function (int $minimum): void {
    climateCompanyMinimum($this->tenant, $minimum);
    Employee::factory()->count($minimum - 1)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->area->id]);
    $survey = Survey::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => resolve(LaunchSurvey::class)->handle($survey->id, new CarbonImmutable('2026-11-20'), $this->user->id))
        ->toThrow(SurveyCannotLaunch::class, "Se necesitan al menos {$minimum} personas para proteger el anonimato.");

    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->area->id]);
    expect(resolve(LaunchSurvey::class)->handle($survey->id, new CarbonImmutable('2026-11-20'), $this->user->id)->status)->toBe(SurveyStatus::Active);
})->with([3, 12]);

it('launches to one person when the company set no minimum, and to nobody it does not', function (): void {
    climateCompanyMinimum($this->tenant, 1);
    $survey = Survey::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => resolve(LaunchSurvey::class)->handle($survey->id, new CarbonImmutable('2026-11-20'), $this->user->id))
        ->toThrow(SurveyCannotLaunch::class, 'Se necesita al menos una persona para lanzar la encuesta.');

    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->area->id]);
    expect(resolve(LaunchSurvey::class)->handle($survey->id, new CarbonImmutable('2026-11-20'), $this->user->id)->invited)->toBe(1);
});

it('promises in the invitation the minimum the survey froze, not the one the company has when it is sent', function (): void {
    climateCompanyMinimum($this->tenant, 3);
    climateLaunchedTo($this, 12);
    climateCompanyMinimum($this->tenant, 12);

    expect(climateInvitationHtml())->toContain('El sistema no guarda quién contestó qué y solo muestra resultados de grupos de 3 personas o más.');
});

it('warns in the invitation, with no minimum, that a very small group could be identified', function (): void {
    climateCompanyMinimum($this->tenant, 1);
    climateLaunchedTo($this, 12);

    expect(climateInvitationHtml())->toContain(e(trans('group-minimum.notice_none')))
        ->not->toContain('grupos de 1');
});

it('shows the results of a closed survey under the minimum it froze, whatever the company has now', function (): void {
    $hr = memberOf($this->tenant, TenantRole::Hr);
    $survey = climateResultsSurvey($this->tenant, 4);
    $survey->update(['min_group' => 3]);
    climateCompanyMinimum($this->tenant, 12);

    $this->actingAs($hr)->get(route('tenant.climate.results', [$this->tenant, $survey]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('minGroup', 3)
        ->where('index', 100));
    $this->actingAs($hr)->get(route('tenant.climate.results.items', [$this->tenant, $survey]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('minGroup', 3));
    $this->actingAs($hr)->get(route('tenant.climate.managers', [$this->tenant, $survey]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('minGroup', 3));
    $this->actingAs($hr)->get(route('tenant.climate.commitments.index', [$this->tenant, $survey]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('minGroup', 3));
});

it('hides the results of a closed survey under a higher frozen minimum, though the company lowered its own', function (): void {
    $hr = memberOf($this->tenant, TenantRole::Hr);
    $survey = climateResultsSurvey($this->tenant, 6);
    $survey->update(['min_group' => 12]);
    climateCompanyMinimum($this->tenant, 1);

    $this->actingAs($hr)->get(route('tenant.climate.results', [$this->tenant, $survey]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('minGroup', 12)
        ->where('index', null));
});

it('counts participation and tells RH the minimum the survey froze', function (): void {
    $hr = memberOf($this->tenant, TenantRole::Hr);
    climateCompanyMinimum($this->tenant, 1);
    $survey = climateLaunchedTo($this, 3);
    climateCompanyMinimum($this->tenant, 12);

    $props = $this->actingAs($hr)->get(route('tenant.climate.show', [$this->tenant, $survey]))->assertOk()->inertiaProps();

    // With no minimum the one group of three is shown; under the company's twelve now, it would not be.
    expect($props['participation']['minGroup'])->toBe(1)
        ->and($props['participation']['cuts'][0]['groups'])->toHaveCount(1);
});

it('tells RH on the draft the minimum the company has now', function (): void {
    $hr = memberOf($this->tenant, TenantRole::Hr);
    $draft = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
    climateCompanyMinimum($this->tenant, 12);

    $props = $this->actingAs($hr)->get(route('tenant.climate.show', [$this->tenant, $draft]))->assertOk()->inertiaProps();

    expect($props['draft']['minGroup'])->toBe(12);
});

it('tells whoever answers the minimum the survey froze', function (): void {
    $survey = Survey::factory()->active('2026-11-20')->create(['tenant_id' => $this->tenant->id, 'min_group' => 3]);
    climateCompanyMinimum($this->tenant, 12);
    $employee = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $invitation = Invitation::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'employee_id' => $employee->id]);
    $invitation->update(['token_hash' => Invitation::hashToken($invitation->surveyToken())]);

    $this->get(route('engagement.survey.show', $invitation->surveyToken()))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('minGroup', 3));
});

it('leaves the minimum of another company alone', function (): void {
    $other = Tenant::factory()->create(['min_group' => 9]);
    $theirs = Survey::factory()->create(['tenant_id' => $other->id]);
    climateCompanyMinimum($this->tenant, 3);

    expect(climateLaunchedTo($this, 5)->fresh()?->min_group)->toBe(3);

    app()->forgetScopedInstances();
    workIn($other);
    expect(Survey::query()->findOrFail($theirs->id)->groupMinimum())->toBe(9);
});

it('tells RH on the home of Clima and of Pulso the minimum the next survey will promise', function (string $home): void {
    $hr = memberOf($this->tenant, TenantRole::Hr);
    climateCompanyMinimum($this->tenant, 12);

    $this->actingAs($hr)->get(route($home, $this->tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('minGroup', 12));
})->with(['tenant.climate.index', 'tenant.pulse.index']);

it('shows a manager the group of four of a survey that froze three, though the company now asks for twelve', function (): void {
    $ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Sol']);
    $survey = climateManagerSurvey($this->tenant, [$ana->id => [4, 5]]);
    $survey->update(['min_group' => 3]);
    climateCompanyMinimum($this->tenant, 12);

    $this->get(route('portal.feedback.show', asPerson($ana->id)))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('feedback.items')
        ->where('feedback.minGroup', 3));
});
