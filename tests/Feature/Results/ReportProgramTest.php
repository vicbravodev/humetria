<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Results\Support\ReportBuilder;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ReportBuilder::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** A closed evaluation of the company whose Guía II was answered `$answers` by `$count` questionnaires. */
function reportedEvaluation(object $test, array $answers, int $count = 5): Campaign
{
    return closedEvaluationWith($test->tenant, $answers, $count);
}

it('tells the report that the Programa is missing when the results ask for one and there is none', function (): void {
    $campaign = reportedEvaluation($this, nom035Answers('nom035.guide-ii', 'always'));

    $this->actingAs($this->hr)->get(route('tenant.campaigns.report', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('report.intervention', ['state' => 'missing', 'program' => null]));
});

it('tells the report that the norm asks for no Programa when nobody is in Medio or more', function (): void {
    $campaign = reportedEvaluation($this, nom035HealthyAnswers('nom035.guide-ii'));

    $this->actingAs($this->hr)->get(route('tenant.campaigns.report', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('report.intervention', ['state' => 'not_required', 'program' => null]));
});

it('tells the report that nothing can be derived under the minimum', function (): void {
    $campaign = reportedEvaluation($this, nom035Answers('nom035.guide-ii', 'always'), 4);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.report', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('report.intervention', ['state' => 'below_minimum', 'program' => null]));
});

it('prints the Programa in the incise g, next to the text HR wrote', function (): void {
    $campaign = reportedEvaluation($this, nom035Answers('nom035.guide-ii', 'always'));
    workIn($this->tenant);
    $program = resolve(CreateProgram::class)->handle($campaign->id, null);
    $row = ProgramItem::query()->where('program_id', $program?->id)->where('scope', 'final')->sole();
    ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $program?->id, 'item_id' => $row->id, 'title' => 'Revisar la política', 'due_on' => '2026-12-15']);
    $this->actingAs($this->hr)->put(route('tenant.campaigns.report.update', [$this->tenant, $campaign->id]), [
        'objective' => 'Objetivo.', 'activities' => 'Actividades.', 'conclusions' => 'Conclusiones.', 'recommendations' => 'Texto de las recomendaciones.',
        'responsible_name' => 'Dra. Laura Méndez', 'responsible_license' => '1234567',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->hr)->get(route('tenant.campaigns.report', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.recommendations', 'Texto de las recomendaciones.')
            ->where('report.intervention.state', 'program')
            ->where('report.intervention.program.asOf', '20 nov 2026')
            ->where('report.intervention.program.state', 'En curso')
            ->where('report.intervention.program.items', fn ($items) => collect($items)->firstWhere('label', 'Calificación final')['actions'][0]['title'] === 'Revisar la política'
                && collect($items)->firstWhere('label', 'Calificación final')['actions'][0]['dueOn'] === '15 dic 2026'));
});

it('reflects a change in the Programa at once: the report keeps no copy', function (): void {
    $campaign = reportedEvaluation($this, nom035Answers('nom035.guide-ii', 'always'));
    workIn($this->tenant);
    $program = resolve(CreateProgram::class)->handle($campaign->id, null);
    $row = ProgramItem::query()->where('program_id', $program?->id)->where('scope', 'final')->sole();
    $url = route('tenant.campaigns.report', [$this->tenant, $campaign->id]);

    $this->actingAs($this->hr)->get($url)->assertInertia(fn (Assert $page) => $page->where('report.intervention.program.meter', ['done' => 0, 'total' => 0]));

    ProgramAction::factory()->done()->create(['tenant_id' => $this->tenant->id, 'program_id' => $program?->id, 'item_id' => $row->id]);

    $this->actingAs($this->hr)->get($url)->assertInertia(fn (Assert $page) => $page->where('report.intervention.program.meter', ['done' => 1, 'total' => 1]));
});

it('carries to a shared link the Programa with no file, no note, no email and no id of a person', function (): void {
    $campaign = reportedEvaluation($this, nom035Answers('nom035.guide-ii', 'always'));
    workIn($this->tenant);
    $program = resolve(CreateProgram::class)->handle($campaign->id, null);
    $row = ProgramItem::query()->where('program_id', $program?->id)->where('scope', 'final')->sole();
    $member = memberOf($this->tenant, TenantRole::Owner);
    $action = ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $program?->id, 'item_id' => $row->id, 'title' => 'Revisar la política', 'responsible_user_id' => $member->id, 'responsible_name' => $member->name, 'created_by' => $member->id]);
    ProgressEntry::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $action->id, 'note' => 'Hablé con Juan Pérez.', 'evidence_path' => 'evidence/secret.pdf']);
    $token = str_repeat('b', 64);
    ReportShare::factory()->create(['campaign_id' => $campaign->id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'expires_at' => now()->addHour()]);

    $response = $this->get(route('report.shared', $token))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('report.intervention.state', 'program')->where('report.intervention.program.items', fn ($items) => collect($items)->firstWhere('label', 'Calificación final')['actions'][0]['responsible'] === $member->name));
    $json = $response->getContent();

    expect($json)->not->toContain('secret.pdf')->not->toContain('Juan Pérez')->not->toContain($member->email)->not->toContain('evidence_path')->not->toContain('responsible_user_id');
});

it('starts the form of the report from the responsible of the Programa, until HR names one', function (): void {
    $campaign = reportedEvaluation($this, nom035Answers('nom035.guide-ii', 'always'));
    workIn($this->tenant);
    $program = resolve(CreateProgram::class)->handle($campaign->id, null);
    $program?->update(['responsible_name' => 'Ing. Carlos Ruiz']);
    $show = fn () => $this->actingAs($this->hr)->get(route('tenant.campaigns.report', [$this->tenant, $campaign->id]));

    $show()->assertInertia(fn (Assert $page) => $page->where('form.responsible_name', 'Ing. Carlos Ruiz')->where('report.responsible', null));

    $this->actingAs($this->hr)->put(route('tenant.campaigns.report.update', [$this->tenant, $campaign->id]), [
        'objective' => 'Objetivo.', 'activities' => 'Actividades.', 'conclusions' => 'Conclusiones.', 'recommendations' => 'Recomendaciones.',
        'responsible_name' => 'Dra. Laura Méndez', 'responsible_license' => '',
    ]);

    $show()->assertInertia(fn (Assert $page) => $page->where('form.responsible_name', 'Dra. Laura Méndez'));
});
