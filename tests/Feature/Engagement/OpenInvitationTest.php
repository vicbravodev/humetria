<?php

declare(strict_types=1);

use App\Modules\Engagement\Actions\OpenInvitation;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(OpenInvitation::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->survey = Survey::factory()->active('2026-11-20')->create(['tenant_id' => $this->tenant->id]);
    $this->manager = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Marta Ruiz']);
});

/**
 * The link of a person invited to the survey of the test, about the manager of the test unless there is none.
 *
 * @param  array<string, mixed>  $attributes
 */
function engagementLink(array $attributes = [], bool $withManager = true): string
{
    $test = test();
    $employee = Employee::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->plant->id]);
    $invitation = Invitation::factory()->create($attributes + [
        'tenant_id' => $test->tenant->id,
        'survey_id' => $test->survey->id,
        'employee_id' => $employee->id,
        'about_manager_id' => $withManager ? $test->manager->id : null,
    ]);
    $invitation->update(['token_hash' => Invitation::hashToken($invitation->surveyToken())]);

    return $invitation->surveyToken();
}

it('opens the 44 questions in four sections with the name of the manager in his instruction', function (): void {
    $token = engagementLink();

    $this->get(route('engagement.survey.show', $token))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('engagement/survey')
        ->where('unavailable', null)
        ->where('token', $token)
        ->where('company', 'Autopartes del Bajío')
        ->where('kind', 'climate')
        ->where('minutes', 12)
        ->where('minGroup', 5)
        ->where('closesOn', '20 de noviembre')
        ->where('managerName', 'Marta Ruiz')
        ->has('sections', 4)
        ->where('sections', fn ($sections) => collect($sections)->sum(fn ($section) => count($section['items'])) === 44)
        ->where('sections.2.instruction', fn (string $text) => str_contains($text, 'Marta Ruiz'))
        ->has('agreementLabels', 5)
        ->has('openNotice'));
});

it('shows no manager section to someone without an active manager', function (): void {
    $token = engagementLink(withManager: false);

    $this->get(route('engagement.survey.show', $token))->assertInertia(fn (Assert $page) => $page
        ->where('managerName', null)
        ->has('sections', 3));
});

it('keeps asking about a manager who was deactivated after the invitation', function (): void {
    $token = engagementLink();
    $this->manager->update(['deactivated_at' => now()]);

    $this->get(route('engagement.survey.show', $token))->assertInertia(fn (Assert $page) => $page
        ->where('managerName', 'Marta Ruiz')
        ->has('sections', 4));
});

it('says why a link does not open, and shows no question', function (string $reason, Closure $arrange): void {
    $token = $arrange();

    $this->get(route('engagement.survey.show', $token))->assertInertia(fn (Assert $page) => $page
        ->component('engagement/survey')
        ->where('unavailable', $reason)
        ->where('sections', [])
        ->where('token', null));
})->with([
    'invented' => ['invalid', fn () => 'not-a-token'],
    'cancelled' => ['invalid', fn () => engagementLink(['cancelled' => true])],
    'suspended' => ['suspended', function () {
        $token = engagementLink();
        test()->tenant->update(['restriction' => 'suspended', 'restriction_reason' => 'Prueba', 'restricted_at' => now()]);

        return $token;
    }],
    'closed' => ['closed', function () {
        $token = engagementLink();
        test()->survey->update(['status' => 'closed', 'closed_at' => now()]);

        return $token;
    }],
    'past its last day' => ['closed', function () {
        $token = engagementLink();
        test()->survey->update(['closes_on' => '2026-11-08']);

        return $token;
    }],
    'answered' => ['done', fn () => engagementLink(['answered' => true])],
]);

it('does not name the company when the link is invalid', function (): void {
    $this->get(route('engagement.survey.show', 'not-a-token'))->assertInertia(fn (Assert $page) => $page->where('company', null));
});

it('opens on the last day and not the day after', function (): void {
    $token = engagementLink();
    $this->survey->update(['closes_on' => '2026-11-09']);
    $this->get(route('engagement.survey.show', $token))->assertInertia(fn (Assert $page) => $page->where('unavailable', null));

    Carbon::setTestNow('2026-11-10 07:00:00');
    $this->get(route('engagement.survey.show', $token))->assertInertia(fn (Assert $page) => $page->where('unavailable', 'closed'));
});

it('puts no employee or invitation id in the props', function (): void {
    $token = engagementLink();
    $invitation = Invitation::query()->sole();

    $json = $this->get(route('engagement.survey.show', $token))->getContent();

    expect($json)->not->toContain($invitation->id)->not->toContain($invitation->employee_id)->not->toContain((string) $invitation->about_manager_id);
});
