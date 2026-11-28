<?php

declare(strict_types=1);

use App\Modules\Assistant\Support\LiveFacts;
use App\Modules\Assistant\Tools\GetCampaignProgress;
use App\Modules\Assistant\Tools\GetRiskResults;
use App\Modules\Assistant\Tools\ListPendingActions;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Telework\Contracts\TeleworkStanding;
use App\Modules\Telework\Data\TeleworkRow;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;

mutates(LiveFacts::class, ListPendingActions::class, GetCampaignProgress::class, GetRiskResults::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->plant = WorkCenter::factory()->create(['tenant_id' => $this->a->id, 'name' => 'Planta Apodaca']);
    $this->hr = memberOf($this->a, TenantRole::Hr);
});

function factsUrl(Tenant $tenant): string
{
    return route('tenant.assistant.facts', $tenant->slug);
}

/** A Guía III campaign of `$tenant` closing in `$days` days, with `$invited` people given the guide and `$answered` of them done. */
function closingCampaign(Tenant $tenant, WorkCenter $center, int $invited, int $answered, int $days = 3): Campaign
{
    $campaign = Campaign::factory()->active(now()->addDays($days)->toDateString())->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'instruments' => ['nom035.guide-i', 'nom035.guide-iii'], 'workers' => 60]);

    foreach (range(1, $invited) as $number) {
        ParticipantInstrument::factory()->create([
            'participant_id' => Participant::factory()->create(['campaign_id' => $campaign->id])->id,
            'instrument' => 'nom035.guide-iii',
            'status' => $number <= $answered ? 'completed' : 'pending',
            'completed_at' => $number <= $answered ? now() : null,
        ]);
    }

    return $campaign;
}

/** A closed evaluation of `$center` with six anonymous Guía II questionnaires: enough for results. */
function evaluatedCenter(Tenant $tenant, WorkCenter $center): Campaign
{
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'workers' => 30]);
    AnonymousResponse::factory()->count(6)->create(['campaign_id' => $closed->id, 'area_id' => Area::factory()->create(['tenant_id' => $tenant->id])->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', 'never')]);

    return $closed;
}

function liveFactsRecordOf(Campaign $campaign): App\Modules\Campaigns\Data\CampaignRecord
{
    workIn(Tenant::query()->findOrFail($campaign->tenant_id));

    return resolve(CampaignRecords::class)->find($campaign->id) ?? throw new LogicException('No such campaign.');
}

it('gives a short real fact for each first question and each page that may invite, from the same tools Humi uses', function (): void {
    $closed = liveFactsRecordOf(evaluatedCenter($this->a, $this->plant));
    $running = liveFactsRecordOf(closingCampaign($this->a, $this->plant, 8, 3));

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))
        ->assertOk()
        ->assertExactJson(['facts' => [
            'participation' => '3 de 8 cuestionarios contestados (38 %)',
            'risk' => "Resultados de la evaluación cerrada el {$closed->closedOn}",
            'pending' => "1 pendiente; vence el {$running->closesOn}",
            'telework' => 'Nadie teletrabaja todavía en tus centros',
            'invite.results' => "Ya hay resultados del {$closed->closedOn}. ¿Te digo dónde está el riesgo más alto?",
            'invite.campaigns' => "Cierra el {$running->closesOn} y faltan 5 cuestionarios. ¿Te cuento cómo va?",
        ]]);
});

it('says why a fact is not there yet instead of making one up', function (): void {
    $this->actingAs($this->hr)->getJson(factsUrl($this->a))
        ->assertJsonPath('facts.participation', 'No hay campañas en curso')
        ->assertJsonPath('facts.risk', 'Se puede ver cuando cierres una evaluación')
        ->assertJsonPath('facts.pending', '1 pendiente')
        ->assertJsonCount(4, 'facts');

    cache()->flush();
    closingCampaign($this->a, $this->plant, 2, 0);

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))->assertJsonPath('facts.risk', 'Se puede ver cuando cierre la campaña');
});

it('counts the pending items and says when the first one was due', function (): void {
    pendingCenterOf($this->a, 'Nunca evaluado');
    $late = pendingCenterOf($this->a, 'Vencido');
    Campaign::factory()->closed()->create(['tenant_id' => $this->a->id, 'work_center_id' => $late->id, 'launched_at' => now()->subYears(3)->subDays(20), 'closed_at' => now()->subYears(3)]);
    workIn($this->a);
    $due = resolve(App\Modules\Campaigns\Contracts\EvaluationSchedule::class)->forWorkCenters([$late->id])[$late->id]->day ?? null;

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))->assertJsonPath('facts.pending', "3 pendientes; el primero venció el {$due}");
});

function pendingCenterOf(Tenant $tenant, string $name): WorkCenter
{
    return WorkCenter::factory()->create(['tenant_id' => $tenant->id, 'name' => $name]);
}

it('tells how many centers with telework have their list complete, and invites to the centers that lack something', function (): void {
    evaluatedCenter($this->a, $this->plant);
    $employee = Employee::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->plant->id]);
    Teleworker::factory()->active()->create(['tenant_id' => $this->a->id, 'employee_id' => $employee->id]);

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))
        ->assertJsonPath('facts.telework', 'Teletrabajo: 0 de 1 centro con su lista completa')
        ->assertJson(['facts' => ['invite.work_centers' => 'A 1 centro le falta algo de su lista de NOM-037. ¿Te explico qué falta?']]);
});

it('invites to no center once every center with telework has its list complete', function (): void {
    evaluatedCenter($this->a, $this->plant);
    app()->instance(TeleworkStanding::class, new readonly class implements TeleworkStanding
    {
        public function forCenters(array $workCenterIds): array
        {
            return array_fill_keys($workCenterIds, [new TeleworkRow('5.1', 'Política de teletrabajo', 'done', 'Cumple', null)]);
        }
    });

    $facts = $this->actingAs($this->hr)->getJson(factsUrl($this->a))->json('facts');

    expect($facts['telework'])->toBe('Teletrabajo: 1 de 1 centro con su lista completa')
        ->and($facts)->not->toHaveKey('invite.work_centers');
});

it('invites to the Programas de intervención when one has actions past their day', function (): void {
    $late = Program::factory()->create(['tenant_id' => $this->a->id, 'campaign_id' => evaluatedCenter($this->a, $this->plant)->id]);
    ProgramAction::factory()->create(['tenant_id' => $this->a->id, 'program_id' => $late->id, 'due_on' => now()->subDays(2)->toDateString()]);

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))
        ->assertJson(['facts' => ['invite.interventions' => '1 programa de intervención tiene acciones vencidas. ¿Lo repasamos?']]);
});

it('tells only the norms the company has', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))->assertExactJson(['facts' => []]);
});

it('gives no participation line while the campaign running has nobody invited yet', function (): void {
    Campaign::factory()->active(now()->addDays(3)->toDateString())->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->plant->id]);

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))
        ->assertOk()
        ->assertJsonMissingPath('facts.participation');
});

it('keeps the facts of a company for a minute, so opening the panel again costs only the session', function (): void {
    closingCampaign($this->a, $this->plant, 4, 1);
    $this->actingAs($this->hr)->getJson(factsUrl($this->a))->assertJsonPath('facts.participation', '1 de 4 cuestionarios contestados (25 %)');
    ParticipantInstrument::query()->where('status', 'pending')->limit(1)->update(['status' => 'completed', 'completed_at' => now()]);

    $this->expectsDatabaseQueryCount(8);
    $this->actingAs($this->hr)->getJson(factsUrl($this->a))->assertJsonPath('facts.participation', '1 de 4 cuestionarios contestados (25 %)');
});

it('reads the facts again after a minute', function (): void {
    closingCampaign($this->a, $this->plant, 4, 1);
    $this->actingAs($this->hr)->getJson(factsUrl($this->a));
    ParticipantInstrument::query()->where('status', 'pending')->limit(1)->update(['status' => 'completed', 'completed_at' => now()]);

    $this->travel(61)->seconds();

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))->assertJsonPath('facts.participation', '2 de 4 cuestionarios contestados (50 %)');
});

it('keeps the facts for sixty seconds and not one more', function (): void {
    $this->freezeSecond();
    closingCampaign($this->a, $this->plant, 4, 1);
    $this->actingAs($this->hr)->getJson(factsUrl($this->a));
    ParticipantInstrument::query()->where('status', 'pending')->limit(1)->update(['status' => 'completed', 'completed_at' => now()]);

    $this->travel(59)->seconds();
    $this->actingAs($this->hr)->getJson(factsUrl($this->a))->assertJsonPath('facts.participation', '1 de 4 cuestionarios contestados (25 %)');

    $this->travel(1)->seconds();
    $this->actingAs($this->hr)->getJson(factsUrl($this->a))->assertJsonPath('facts.participation', '2 de 4 cuestionarios contestados (50 %)');
});

it('never tells the facts of another company, and keeps them apart', function (): void {
    closingCampaign($this->b, WorkCenter::factory()->create(['tenant_id' => $this->b->id]), 6, 6);
    $this->actingAs(memberOf($this->b, TenantRole::Hr))->getJson(factsUrl($this->b))->assertJsonPath('facts.participation', '6 de 6 cuestionarios contestados (100 %)');

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))->assertJsonPath('facts.participation', 'No hay campañas en curso');
    $this->actingAs($this->hr)->getJson(factsUrl($this->b))->assertNotFound();
});

it('is closed to a guest and to a role that may not use Humi', function (): void {
    $this->getJson(factsUrl($this->a))->assertUnauthorized();
    $this->actingAs(memberOf($this->a, TenantRole::Committee))->getJson(factsUrl($this->a))->assertForbidden();
});

it('says one or many as it is: campaigns, questionnaires, centers and Programas', function (): void {
    $south = WorkCenter::factory()->create(['tenant_id' => $this->a->id, 'name' => 'Planta Sur']);
    $first = liveFactsRecordOf(closingCampaign($this->a, $this->plant, 2, 1));
    closingCampaign($this->a, $south, 3, 1, 5);
    foreach ([$this->plant, $south] as $center) {
        $late = Program::factory()->create(['tenant_id' => $this->a->id, 'campaign_id' => evaluatedCenter($this->a, $center)->id]);
        ProgramAction::factory()->create(['tenant_id' => $this->a->id, 'program_id' => $late->id, 'due_on' => now()->subDays(2)->toDateString()]);
        Teleworker::factory()->active()->create(['tenant_id' => $this->a->id, 'employee_id' => Employee::factory()->create(['tenant_id' => $this->a->id, 'work_center_id' => $center->id])->id]);
    }

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))
        ->assertJsonPath('facts.participation', 'En 2 campañas: 2 de 5 cuestionarios contestados (40 %)')
        ->assertJson(['facts' => [
            'invite.campaigns' => "Cierra el {$first->closesOn} y falta 1 cuestionario. ¿Te cuento cómo va?",
            'invite.interventions' => '2 programas de intervención tienen acciones vencidas. ¿Los repasamos?',
            'invite.work_centers' => 'A 2 centros les falta algo de su lista de NOM-037. ¿Te explico qué falta?',
        ]])
        ->assertJsonPath('facts.telework', 'Teletrabajo: 0 de 2 centros con su lista completa');
});

it('does not invite to results once they are a month old', function (): void {
    $this->travelTo(now()->subDays(31));
    evaluatedCenter($this->a, $this->plant);
    $this->travelBack();

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))
        ->assertJsonPath('facts.risk', fn (string $risk): bool => str_starts_with($risk, 'Resultados de la evaluación cerrada el '))
        ->assertJsonCount(4, 'facts');
});

it('still invites to results thirty days after they closed', function (): void {
    $noon = now(config()->string('app.display_timezone'))->setTime(12, 0);
    $this->travelTo($noon->subDays(30));
    $closed = liveFactsRecordOf(evaluatedCenter($this->a, $this->plant));
    $this->travelTo($noon);

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))
        ->assertJson(['facts' => ['invite.results' => "Ya hay resultados del {$closed->closedOn}. ¿Te digo dónde está el riesgo más alto?"]]);
});

it('says why the latest closed evaluation has no results instead of inviting to them', function (): void {
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->plant->id, 'workers' => 30]);
    AnonymousResponse::factory()->count(4)->create(['campaign_id' => $closed->id, 'area_id' => Area::factory()->create(['tenant_id' => $this->a->id])->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', 'never')]);

    $this->actingAs($this->hr)->getJson(factsUrl($this->a))
        ->assertJsonPath('facts.risk', 'Todavía no hay suficientes respuestas en este grupo para mostrar resultados.')
        ->assertJsonCount(4, 'facts');
});
