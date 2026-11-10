<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Http\Controllers\TrendController;
use App\Modules\Engagement\Support\Bank\Questionnaire;
use App\Modules\Engagement\Support\Pulse\TrendPage;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(TrendController::class, TrendPage::class);

beforeEach(function (): void {
    $this->travelTo('2026-12-20 18:00:00');
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** Every question answered 4 by half of the people and `$other` by the rest: a favorable share of about half. */
function trendAnswers(int $other): Closure
{
    $keys = array_map(fn (App\Modules\Engagement\Data\BankItem $item): string => $item->key, App\Modules\Engagement\Support\Bank\ItemBank::closed());

    return function (int $i) use ($keys, $other): array {
        $answers = [];

        foreach ($keys as $key) {
            $answers[$key] = $key === 'eng.recommend' ? ($i % 2 === 0 ? 10 : 3) : ($i % 2 === 0 ? 4 : $other);
        }

        return $answers;
    };
}

/** A closed survey of the company, closed in `$closedAt`, with `$count` responses. */
function trendWaveSurvey(Tenant $tenant, string $closedAt, int $count = 40, ?Closure $answers = null, array $attributes = []): App\Modules\Engagement\Models\Survey
{
    $survey = climateResultsSurvey($tenant, $count, $answers);
    $survey->update(['closed_at' => $closedAt, ...$attributes]);

    return $survey;
}

it('draws the engagement index of two waves, one column each, with the arrow between them', function (): void {
    trendWaveSurvey($this->tenant, '2026-10-20 12:00:00');
    trendWaveSurvey($this->tenant, '2026-11-20 12:00:00', answers: trendAnswers(2));

    $this->actingAs($this->hr)->get(route('tenant.pulse.trend', $this->tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('engagement/trend')
        ->where('hasWaves', true)
        ->where('series.0.key', 'index')
        ->where('series.0.title', 'Índice de compromiso')
        ->has('series.0.points', 2)
        ->where('series.0.points.0.label', 'oct 2026')
        ->where('series.0.points.0.value', 100)
        ->where('series.0.points.0.direction', null)
        ->where('series.0.points.1.label', 'nov 2026')
        ->where('series.0.points.1.value', 50)
        ->where('series.0.points.1.direction', 'down'));
});

it('lists the engagement, recommendation, improvements seen and the ten themes, in that order', function (): void {
    trendWaveSurvey($this->tenant, '2026-11-20 12:00:00');

    $series = $this->actingAs($this->hr)->get(route('tenant.pulse.trend', $this->tenant))->inertiaProps()['series'];

    expect($series)->toHaveCount(13)
        ->and(array_slice(array_column($series, 'key'), 0, 3))->toBe(['index', 'recommendation', 'loop.action'])
        ->and(array_column($series, 'title'))->toContain('Índice de recomendación', 'Claridad y alineación', 'Compensación y prestaciones');
});

it('has no arrow on a series with a single point', function (): void {
    trendWaveSurvey($this->tenant, '2026-10-20 12:00:00');
    $second = trendWaveSurvey($this->tenant, '2026-11-20 12:00:00', answers: fn (int $i): array => [...trendAnswers(4)($i), 'loop.action' => 4]);
    $second->update(['items' => Questionnaire::climate(true)]);

    $this->actingAs($this->hr)->get(route('tenant.pulse.trend', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('series.2.key', 'loop.action')
        ->has('series.2.points', 1)
        ->where('series.2.points.0.direction', null)
        ->has('series.0.points', 2));
});

it('leaves out a wave where the question was hidden for being small', function (): void {
    trendWaveSurvey($this->tenant, '2026-10-20 12:00:00');
    trendWaveSurvey($this->tenant, '2026-11-20 12:00:00', count: 4);

    $this->actingAs($this->hr)->get(route('tenant.pulse.trend', $this->tenant))->assertInertia(fn (Assert $page) => $page->has('series.0.points', 1));
});

it('says there is nothing to draw yet, with the next step, when no survey closed', function (): void {
    climateResultsSurvey($this->tenant, 6, closed: false);

    $this->actingAs($this->hr)->get(route('tenant.pulse.trend', $this->tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('hasWaves', false)
        ->where('series', [])
        ->where('canManage', true));
});

it('keeps the twelve most recent waves of fourteen', function (): void {
    foreach (range(0, 13) as $month) {
        trendWaveSurvey($this->tenant, now()->subMonths(14 - $month)->setTime(12, 0)->toDateTimeString(), count: 5);
    }

    $this->actingAs($this->hr)->get(route('tenant.pulse.trend', $this->tenant))->assertInertia(fn (Assert $page) => $page->has('series.0.points', 12));
});

it('reads each wave once, whatever the number of series', function (int $waves, int $queries): void {
    foreach (range(1, $waves) as $month) {
        trendWaveSurvey($this->tenant, now()->subMonths($month)->setTime(12, 0)->toDateTimeString(), count: 6);
    }
    $this->actingAs($this->hr);

    // The session, the company and the panel frame, the closed surveys, and one read of the responses per wave.
    $this->expectsDatabaseQueryCount($queries);
    $this->get(route('tenant.pulse.trend', $this->tenant))->assertOk();
})->with([[3, 16], [5, 18]]);

it('takes the company only: no cut is accepted', function (string $query): void {
    trendWaveSurvey($this->tenant, '2026-11-20 12:00:00', count: 6);

    $this->actingAs($this->hr)->getJson(route('tenant.pulse.trend', $this->tenant).$query)->assertUnprocessable();
})->with(['area' => '?cut=area', 'work center' => '?cut=work_center']);

it('shows every role that views the pulse, and no other', function (): void {
    trendWaveSurvey($this->tenant, '2026-11-20 12:00:00', count: 6);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.pulse.trend', $this->tenant))->assertOk();
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(route('tenant.pulse.trend', $this->tenant))->assertForbidden();
});

it('does not read the surveys of another company', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    trendWaveSurvey($other, '2026-11-20 12:00:00');
    workIn($this->tenant);

    $this->actingAs($this->hr)->get(route('tenant.pulse.trend', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('hasWaves', false));
    $this->get(route('tenant.pulse.trend', $other))->assertNotFound();
});

it('never puts a response, a comment or a count of a group in the props, nor the day of a wave', function (): void {
    $survey = trendWaveSurvey($this->tenant, '2026-11-20 12:00:00', count: 12);
    App\Modules\Engagement\Models\Comment::factory()->count(12)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id]);

    $props = $this->actingAs($this->hr)->get(route('tenant.pulse.trend', $this->tenant))->inertiaProps();

    expectNoEngagementResponses($props);
    expect(json_encode($props))->not->toContain('2026-11-20')->not->toContain('"responses"')->not->toContain('"answered"')->not->toContain('eNPS')->not->toContain('Net Promoter');
});

it('audits the view with the number of waves and nothing else', function (): void {
    trendWaveSurvey($this->tenant, '2026-11-20 12:00:00', count: 6);

    $this->actingAs($this->hr)->get(route('tenant.pulse.trend', $this->tenant));

    $entry = AuditEntry::query()->where('event', 'engagement.results_viewed')->sole();
    expect($entry->metadata)->toBe(['view' => 'trend', 'waves' => 1]);
});

it('draws climate and pulse waves together', function (): void {
    trendWaveSurvey($this->tenant, '2026-10-20 12:00:00', count: 6);
    trendWaveSurvey($this->tenant, '2026-11-20 12:00:00', count: 6, attributes: ['kind' => SurveyKind::Pulse, 'pulse_mode' => 'tour']);

    $this->actingAs($this->hr)->get(route('tenant.pulse.trend', $this->tenant))->assertInertia(fn (Assert $page) => $page->has('series.0.points', 2));
});
