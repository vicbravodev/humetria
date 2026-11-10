<?php

declare(strict_types=1);

use App\Modules\Engagement\Actions\CreatePulseDraft;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Http\Requests\PulseDraftRequest;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SurveyInvitation;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CreatePulseDraft::class, PulseDraftRequest::class);

beforeEach(function (): void {
    $this->travelTo('2026-12-09 18:00:00');
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** @param list<array{driver: string, level: string}>|null $priorities */
function pulseClimateBefore(Tenant $tenant, ?array $priorities, string $closedAt = '2026-11-20 12:00:00'): Survey
{
    return Survey::factory()->closed()->create(['tenant_id' => $tenant->id, 'closed_at' => $closedAt, 'priorities' => $priorities]);
}

function pulseDraftPost(mixed $test, Tenant $tenant, string $mode): Illuminate\Testing\TestResponse
{
    return $test->post(route('tenant.pulse.store', $tenant), ['mode' => $mode]);
}

it('drafts a tour with the five fixed questions and five anchors once a survey was closed before', function (): void {
    pulseClimateBefore($this->tenant, null);

    pulseDraftPost($this->actingAs($this->hr), $this->tenant, 'tour')->assertRedirect();

    $pulse = Survey::query()->where('kind', SurveyKind::Pulse)->sole();
    expect($pulse->status)->toBe(SurveyStatus::Draft)
        ->and($pulse->pulse_mode)->toBe(PulseMode::Tour)
        ->and($pulse->items)->toHaveCount(10)
        ->and($pulse->items)->toContain('loop.action@1')
        ->and($pulse->name)->toBe('Pulso · diciembre 2026');
});

it('drafts nine questions when the company has no earlier survey', function (): void {
    pulseDraftPost($this->actingAs($this->hr), $this->tenant, 'tour');

    expect(Survey::query()->sole()->items)->toHaveCount(9)->not->toContain('loop.action@1');
});

it('refuses a follow-up with nothing to follow', function (): void {
    pulseClimateBefore($this->tenant, null);
    pulseClimateBefore($this->tenant, []);

    pulseDraftPost($this->actingAs($this->hr), $this->tenant, 'follow_up')->assertSessionHasErrors(['mode' => 'Aún no hay prioridades de un clima para dar seguimiento.']);

    expect(Survey::query()->where('kind', SurveyKind::Pulse)->count())->toBe(0);
});

it('follows the two first drivers of the latest closed climate, not an earlier one', function (): void {
    pulseClimateBefore($this->tenant, [['driver' => 'reward', 'level' => 'high']], '2026-05-01 12:00:00');
    pulseClimateBefore($this->tenant, [['driver' => 'growth', 'level' => 'high'], ['driver' => 'voice', 'level' => 'medium'], ['driver' => 'team', 'level' => 'medium']]);

    pulseDraftPost($this->actingAs($this->hr), $this->tenant, 'follow_up');

    $items = Survey::query()->where('kind', SurveyKind::Pulse)->sole()->items;
    expect($items)->toContain('growth.apply@1', 'growth.path@1', 'voice.disagree@1')->not->toContain('reward.pay@1')->not->toContain('team.respect@1')
        ->and(Survey::query()->where('kind', SurveyKind::Pulse)->sole()->pulse_mode)->toBe(PulseMode::FollowUp);
});

it('uses the other half of the anchors for the second tour', function (): void {
    pulseClimateBefore($this->tenant, null);
    Survey::factory()->pulse()->closed()->create(['tenant_id' => $this->tenant->id, 'pulse_mode' => PulseMode::Tour]);
    Survey::factory()->pulse()->closed()->create(['tenant_id' => $this->tenant->id, 'pulse_mode' => PulseMode::Manager]);

    pulseDraftPost($this->actingAs($this->hr), $this->tenant, 'tour');

    expect(Survey::query()->where('status', SurveyStatus::Draft)->sole()->items)->toContain('autonomy.organize@1', 'reward.pay@1')->not->toContain('clarity.contribution@1');
});

it('does not open a pulse while another is in preparation or in course, but does beside a climate draft', function (): void {
    $this->actingAs($this->hr);
    Survey::factory()->create(['tenant_id' => $this->tenant->id]);

    pulseDraftPost($this, $this->tenant, 'tour')->assertRedirect();
    expect(Survey::query()->where('kind', SurveyKind::Pulse)->count())->toBe(1);

    pulseDraftPost($this, $this->tenant, 'manager')->assertRedirect(route('tenant.pulse.index', $this->tenant));
    expect(Survey::query()->where('kind', SurveyKind::Pulse)->count())->toBe(1);
});

it('validates the mode', function (?string $mode): void {
    $this->actingAs($this->hr)->post(route('tenant.pulse.store', $this->tenant), $mode === null ? [] : ['mode' => $mode])->assertSessionHasErrors('mode');
})->with([null, 'always_open', 'weekly', '']);

it('keeps a viewer from drafting a pulse', function (): void {
    pulseDraftPost($this->actingAs(memberOf($this->tenant, TenantRole::Viewer)), $this->tenant, 'tour')->assertForbidden();

    expect(Survey::query()->count())->toBe(0);
});

it('writes the log of the draft with the mode and the count, nothing else', function (): void {
    Log::spy();

    pulseDraftPost($this->actingAs($this->hr), $this->tenant, 'manager');

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'engagement.pulse_drafted' && $context['mode'] === 'manager' && $context['items'] === 10)->once();
});

it('shows the mode and the questions of the draft, and offers only the modes available', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('modes', [['value' => 'tour', 'label' => 'Recorrido'], ['value' => 'manager', 'label' => 'Jefe']]));

    pulseClimateBefore($this->tenant, [['driver' => 'growth', 'level' => 'high']]);
    $this->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->has('modes', 3)->where('modes.0.value', 'follow_up'));

    pulseDraftPost($this, $this->tenant, 'tour');
    $pulse = Survey::query()->where('kind', SurveyKind::Pulse)->sole();

    $this->get(route('tenant.pulse.show', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page
        ->where('survey.mode', ['value' => 'tour', 'label' => 'Recorrido'])
        ->has('sections'));
});

it('leaves the questions of a pulse draft with no route to edit them', function (): void {
    $pulse = Survey::factory()->pulse()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->hr)->put(route('tenant.pulse.show', [$this->tenant, $pulse]), ['items' => []])->assertStatus(405);
    expect($pulse->fresh()?->items)->toBe($pulse->items);
});

it('tells the people the pulse takes about two minutes', function (): void {
    $company = new CompanySummary('t', 'Autopartes', 'autopartes', false);
    $html = (string) (new SurveyInvitation(false, 'tok', 'Ana', $company, null, CarbonImmutable::parse('2026-12-20'), SurveyKind::Pulse))->toMail(new AnonymousNotifiable)->render();

    expect($html)->toContain('Unos 2 minutos');
});

it('stores nothing but the mode in a pulse and nothing in a climate', function (): void {
    $climate = Survey::factory()->create(['tenant_id' => $this->tenant->id]);

    expect($climate->pulse_mode)->toBeNull()
        ->and(fn () => Survey::query()->where('id', $climate->id)->update(['pulse_mode' => 'tour']))->toThrow(Illuminate\Database\QueryException::class)
        ->and(fn () => Survey::factory()->create(['tenant_id' => $this->tenant->id, 'kind' => SurveyKind::Pulse, 'status' => SurveyStatus::Closed, 'launched_at' => now(), 'closes_on' => now(), 'closed_at' => now(), 'pulse_mode' => null]))->toThrow(Illuminate\Database\QueryException::class);
});
