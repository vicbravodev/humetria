<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Events\SurveyClosed;
use App\Modules\Engagement\Jobs\SummarizeComments;
use App\Modules\Engagement\Listeners\QueueCommentSummaries;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

it('opens the results, the commitments and the managers of a pulse under its own names', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 6);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $pulse]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('engagement/results')->where('survey.id', $pulse->id)->where('kind', 'pulse'));
    $this->get(route('tenant.pulse.results.items', [$this->tenant, $pulse]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('engagement/items')->where('kind', 'pulse'));
    $this->get(route('tenant.pulse.commitments.index', [$this->tenant, $pulse]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('engagement/commitments')->where('kind', 'pulse'));
    $this->get(route('tenant.pulse.managers', [$this->tenant, $pulse]))->assertOk();
    $this->get(route('tenant.pulse.export', [$this->tenant, $pulse]))->assertOk();
});

it('does not open a pulse by the climate results, nor a climate survey by the pulse ones', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 6);
    $climate = climateResultsSurvey($this->tenant, 6);
    $this->actingAs($this->hr);

    $this->get(route('tenant.climate.results', [$this->tenant, $pulse]))->assertNotFound();
    $this->get(route('tenant.pulse.results', [$this->tenant, $climate]))->assertNotFound();
    $this->get(route('tenant.pulse.commitments.index', [$this->tenant, $climate]))->assertNotFound();
});

it('tells how many have seen the improvements the company presented, once five have answered', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 12);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page
        ->where('loopAction', ['shown' => true, 'favorable' => 100, 'vsPrevious' => null]));
    $this->get(route('tenant.pulse.commitments.index', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page
        ->where('loopAction.shown', true)->where('loopAction.favorable', 100));
});

it('gives no number for the improvements seen under five answers, and none for a survey that did not ask', function (): void {
    $few = pulseResultsSurvey($this->tenant, PulseMode::Tour, 4);
    $climate = climateResultsSurvey($this->tenant, 6);
    $climate->update(['items' => array_values(array_filter($climate->items, fn (string $ref): bool => ! str_starts_with($ref, 'loop.action@')))]);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $few]))->assertInertia(fn (Assert $page) => $page
        ->where('loopAction', ['shown' => false, 'favorable' => null, 'vsPrevious' => null]));
    $this->get(route('tenant.climate.results', [$this->tenant, $climate]))->assertInertia(fn (Assert $page) => $page->where('loopAction', null));
});

it('compares the improvements seen with the previous pulse', function (): void {
    $before = pulseResultsSurvey($this->tenant, PulseMode::Tour, 40, 2);
    $before->update(['closed_at' => now()->subMonth()]);
    $now = pulseResultsSurvey($this->tenant, PulseMode::Tour, 40, 4);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $now]))->assertInertia(fn (Assert $page) => $page
        ->where('loopAction.vsPrevious', ['points' => 100, 'direction' => 'up']));
});

it('shows only the drivers a pulse asked about', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 6);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page
        ->has('drivers', 5)->where('drivers.0.key', 'clarity'));
});

it('gives the manager of a manager pulse only their two questions, with no strengths, opportunities or summary', function (): void {
    $ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Sol']);
    pulseResultsSurvey($this->tenant, PulseMode::Manager, 6, manager: $ana->id);

    $this->get(route('portal.feedback.show', (string) resolve(IssuePortalLink::class)->handle($ana->id)))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('feedback.items', 2)
            ->where('feedback.items.0.key', 'manager.priorities')
            ->where('feedback.strengths', [])
            ->where('feedback.opportunities', [])
            ->where('feedback.summary', null));
});

it('keeps the last survey with all the questions about the manager when a pulse without them closes later', function (): void {
    $ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Sol']);
    $climate = climateManagerSurvey($this->tenant, [$ana->id => [6, 5]]);
    $climate->update(['closed_at' => now()->subMonths(2)]);
    pulseResultsSurvey($this->tenant, PulseMode::Tour, 6, manager: $ana->id);

    $this->get(route('portal.feedback.show', (string) resolve(IssuePortalLink::class)->handle($ana->id)))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('survey.name', $climate->name)->has('feedback.items', 8));
});

it('does not queue any summary of comments when a pulse closes', function (): void {
    Queue::fake();
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 6);

    resolve(QueueCommentSummaries::class)->handle(new SurveyClosed($pulse->id, $pulse->kind));

    Queue::assertNotPushed(SummarizeComments::class);
});

it('gives a theme the pulse asked only in part question by question: the figure the trend draws, not a hidden one', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 6);
    $anchor = ItemBank::anchor(Driver::Clarity)->key;
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page
        ->where('drivers.0.key', 'clarity')->where('drivers.0.partial', true)->where('drivers.0.favorable', null)
        ->where('drivers.0.items', [['key' => $anchor, 'text' => ItemBank::find($anchor)->text, 'favorable' => 100]]));
    $this->get(route('tenant.pulse.trend', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('series.3.key', $anchor)->where('series.3.points.0.surveyId', $pulse->id)->where('series.3.points.0.value', 100));
});

it('hides an anchor under five answers in the results and in the trend alike', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 4);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page
        ->where('drivers.0.partial', true)->where('drivers.0.favorable', null)->where('drivers.0.items.0.favorable', null));
    $this->get(route('tenant.pulse.trend', $this->tenant))->assertInertia(fn (Assert $page) => $page->has('series.3.points', 0));
});

it('says a theme was asked in part without any count of answers nor how many are missing', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 4);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page
        ->has('drivers.0', fn (Assert $driver) => $driver->hasAll(['key', 'label', 'descriptive', 'partial', 'favorable', 'delta'])
            ->has('items', 1, fn (Assert $item) => $item->hasAll(['key', 'text', 'favorable']))));
});
