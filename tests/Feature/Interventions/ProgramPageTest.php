<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Http\Controllers\ProgramController;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Support\ProgramPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ProgramController::class, ProgramPages::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
});

/** A closed evaluation of the plant whose Guía II questionnaires were answered `$value`. */
function plantEvaluation(object $test, string $value, int $count = 5): Campaign
{
    $campaign = closedEvaluationWith($test->tenant, nom035Answers('nom035.guide-ii', $value), $count);
    $campaign->update(['work_center_id' => $test->plant->id, 'closed_at' => '2026-11-19 18:00:00']);

    return $campaign;
}

it('shows the Programa of a closed evaluation with its rows, from the most general to the most specific', function (): void {
    $campaign = plantEvaluation($this, 'always');
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.store', [$this->tenant, $campaign->id]))->assertRedirect();

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.program', [$this->tenant, $campaign->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('interventions/show')
            ->where('campaign', ['id' => $campaign->id, 'workCenter' => 'Planta Norte', 'kindLabel' => 'Evaluación NOM-035', 'closedOn' => '19 nov 2026', 'guide' => 'Guía II'])
            ->where('requirement', null)
            ->where('program.highestLevel', ['value' => 'very_high', 'label' => 'Muy alto'])
            ->where('program.responsible', null)
            ->where('program.items.0.scope', 'final')
            ->where('program.items.0.label', 'Calificación final')
            ->where('program.items.0.level', ['value' => 'very_high', 'label' => 'Muy alto'])
            ->where('program.items.0.manual', false)
            ->where('program.items.1.scope', 'category')
            ->where('can', ['manage' => true]));
});

it('says what to do when there is no Programa yet: create it, or that none is needed', function (string $case, string $requirement): void {
    $campaign = match ($case) {
        'risk' => plantEvaluation($this, 'always'),
        'healthy' => closedEvaluationWith($this->tenant, nom035HealthyAnswers('nom035.guide-ii')),
        default => plantEvaluation($this, 'always', 4),
    };

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.program', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('program', null)->where('requirement', $requirement));
})->with([
    'results in risk' => ['risk', 'required'],
    'nobody in risk' => ['healthy', 'not_required'],
    'under the minimum' => ['few', 'below_minimum'],
]);

it('creates the Programa with the button, once, and leaves the rest to the page', function (): void {
    $campaign = plantEvaluation($this, 'always');
    $url = route('tenant.campaigns.program.store', [$this->tenant, $campaign->id]);

    $this->actingAs($this->hr)->post($url)->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $campaign->id]));
    $this->actingAs($this->hr)->post($url)->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $campaign->id]));

    expect(Program::query()->count())->toBe(1)
        ->and(Program::query()->sole()->created_by)->toBe($this->hr->id)
        ->and(ProgramItem::query()->count())->toBeGreaterThan(0);
});

it('creates nothing and says so when the norm asks for no Programa', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035HealthyAnswers('nom035.guide-ii'));

    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.store', [$this->tenant, $campaign->id]))
        ->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $campaign->id]));

    expect(Program::query()->count())->toBe(0);
});

it('audits who opened the Programa, with no figure', function (): void {
    $campaign = plantEvaluation($this, 'always');

    $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $campaign->id]))->assertOk();

    expect(AuditEntry::query()->where('event', 'interventions.program_viewed')->sole()->metadata)->toBe(['campaign_id' => $campaign->id]);
});

it('lets the viewer read the Programa and not create it', function (): void {
    $campaign = plantEvaluation($this, 'always');
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($viewer)->get(route('tenant.campaigns.program', [$this->tenant, $campaign->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('can', ['manage' => false]));
    $this->actingAs($viewer)->post(route('tenant.campaigns.program.store', [$this->tenant, $campaign->id]))->assertForbidden();

    expect(Program::query()->count())->toBe(0);
});

it('keeps the Comisión out of the Programa', function (): void {
    $campaign = plantEvaluation($this, 'always');
    $committee = memberOf($this->tenant, TenantRole::Committee);

    $this->actingAs($committee)->get(route('tenant.campaigns.program', [$this->tenant, $campaign->id]))->assertForbidden();
    $this->actingAs($committee)->post(route('tenant.campaigns.program.store', [$this->tenant, $campaign->id]))->assertForbidden();
});

it('answers 404 for an evaluation that has no Programa to speak of', function (): void {
    $open = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id]);
    $trauma = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id]);
    $trauma->update(['status' => 'closed', 'closed_at' => now()]);

    foreach ([$open->id, $trauma->id, '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c'] as $id) {
        $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $id]))->assertNotFound();
        $this->actingAs($this->hr)->post(route('tenant.campaigns.program.store', [$this->tenant, $id]))->assertNotFound();
    }
});

it('answers 404, never 403, for the evaluation or the Programa of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = closedEvaluationWith($other, nom035Answers('nom035.guide-ii', 'always'));
    workIn($other);
    resolve(CreateProgram::class)->handle($foreign->id, null);
    workIn($this->tenant);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.store', [$this->tenant, $foreign->id]))->assertNotFound();

    workIn($other);
    expect(Program::query()->count())->toBe(1);
});

it('sends a guest to sign in', function (): void {
    $campaign = plantEvaluation($this, 'always');

    $this->get(route('tenant.campaigns.program', [$this->tenant, $campaign->id]))->assertRedirect(route('login'));
});
