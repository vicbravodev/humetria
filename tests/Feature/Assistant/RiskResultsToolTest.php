<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Assistant\Tools\GetRiskResults;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Str;

mutates(GetRiskResults::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->plant = WorkCenter::factory()->create(['tenant_id' => $this->a->id, 'name' => 'Planta Apodaca']);
    $this->production = Area::factory()->create(['tenant_id' => $this->a->id, 'name' => 'Producción']);
    $this->sales = Area::factory()->create(['tenant_id' => $this->a->id, 'name' => 'Ventas']);
    $this->closed = Campaign::factory()->closed()->create(['tenant_id' => $this->a->id, 'work_center_id' => $this->plant->id, 'workers' => 30]);
    $this->scope = assistantScopeIn($this->a);
});

/** `$count` anonymous Guía II questionnaires of the campaign in `$area`, all answered `never`. */
function riskAnswers(Campaign $campaign, Area $area, int $count): void
{
    AnonymousResponse::factory()->count($count)->create(['campaign_id' => $campaign->id, 'area_id' => $area->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', 'never')]);
}

it('gives the closed evaluation by risk level: the final score, the categories and the domains, as people in each level', function (): void {
    riskAnswers($this->closed, $this->production, 6);

    $card = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $this->closed->id]);

    expect($card['render'])->toBe('risk_levels')
        ->and($card['minGroup'])->toBe(5)
        ->and($card['link'])->toBe("/admin/{$this->a->slug}/campaigns/{$this->closed->id}/results")
        ->and($card['rows'][0])->toMatchArray(['kind' => 'final', 'answered' => 6, 'levels' => ['Nulo o despreciable' => 0, 'Bajo' => 0, 'Medio' => 6, 'Alto' => 0, 'Muy alto' => 0]])
        ->and(array_unique(array_column($card['rows'], 'kind')))->toContain('category', 'domain');
});

it('shows a group of five and says there are too few answers in one of four, without a figure', function (): void {
    riskAnswers($this->closed, $this->production, 4);
    $four = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $this->closed->id]);

    riskAnswers($this->closed, $this->production, 1);
    $five = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $this->closed->id]);

    expect($four['rows'])->toBe([])
        ->and($four['note'])->toBe('Todavía no hay suficientes respuestas en este grupo para mostrar resultados.')
        ->and($four['minGroup'])->toBe(5)
        ->and(json_encode($four, JSON_THROW_ON_ERROR))->not->toContain('answered')
        ->and($five['rows'])->not->toBeEmpty();
});

it('applies the minimum after narrowing to an area', function (): void {
    riskAnswers($this->closed, $this->production, 6);
    riskAnswers($this->closed, $this->sales, 5);

    $production = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $this->closed->id, 'area_id' => $this->production->id]);

    expect($production['title'])->toBe('Resultados de Producción')
        ->and($production['rows'][0]['answered'])->toBe(6);
});

it('does not show an area apart that stays hidden: it gives the center and says so', function (): void {
    riskAnswers($this->closed, $this->production, 6);
    riskAnswers($this->closed, $this->sales, 3);

    $card = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $this->closed->id, 'area_id' => $this->sales->id]);

    expect($card['rows'][0]['answered'])->toBe(9)
        ->and($card['note'])->toContain('No puedo mostrar esa área por separado');
});

it('says how many areas stay hidden and lists those shown', function (): void {
    $third = Area::factory()->create(['tenant_id' => $this->a->id, 'name' => 'Logística']);
    riskAnswers($this->closed, $this->production, 6);
    riskAnswers($this->closed, $this->sales, 5);
    riskAnswers($this->closed, $third, 3);

    $card = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $this->closed->id]);
    $areas = array_values(array_filter($card['rows'], fn (array $row): bool => $row['kind'] === 'area'));

    expect(array_column($areas, 'label'))->toBe(['Producción'])
        ->and($card['note'])->toBe('2 áreas no se muestran por separado porque tienen pocas respuestas.');
});

it('gives no result while the evaluation is open or for a Guía I campaign, and says why', function (): void {
    $open = Campaign::factory()->active()->create(['tenant_id' => $this->a->id, 'work_center_id' => WorkCenter::factory()->create(['tenant_id' => $this->a->id])->id]);
    riskAnswers($open, $this->production, 10);
    $trauma = Campaign::factory()->trauma()->create(['tenant_id' => $this->a->id, 'work_center_id' => WorkCenter::factory()->create(['tenant_id' => $this->a->id])->id]);
    $trauma->update(['status' => 'closed', 'closed_at' => now()]);

    $openCard = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $open->id]);
    $traumaCard = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $trauma->id]);

    expect($openCard['rows'])->toBe([])
        ->and($openCard['note'])->toBe('La evaluación sigue abierta: los resultados aparecen al cerrarla.')
        ->and($traumaCard['rows'])->toBe([])
        ->and($traumaCard['note'])->toBe('Esta campaña solo aplica la Guía I y no tiene resultados por nivel de riesgo.');
});

it('answers for a campaign of another company exactly as for one that does not exist', function (): void {
    $plantB = WorkCenter::factory()->create(['tenant_id' => $this->b->id]);
    $foreign = Campaign::factory()->closed()->create(['tenant_id' => $this->b->id, 'work_center_id' => $plantB->id]);
    riskAnswers($foreign, Area::factory()->create(['tenant_id' => $this->b->id]), 8);

    $ofAnother = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $foreign->id]);
    $missing = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => (string) Str::uuid()]);
    $notAnId = toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => 'todo']);
    $noId = toolCard(GetRiskResults::class, $this->scope, []);

    expect($ofAnother)->toBe($missing)
        ->and($notAnId)->toBe($missing)
        ->and($noId)->toBe($missing)
        ->and($missing['rows'])->toBe([])
        ->and($missing['note'])->toBe('No encontré esa campaña en tu empresa.');
});

it('leaves a trace in the audit trail, without content, when it shows results and only then', function (): void {
    riskAnswers($this->closed, $this->production, 4);
    toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $this->closed->id]);
    expect(AuditEntry::query()->where('event', 'assistant.results_viewed')->count())->toBe(0);

    riskAnswers($this->closed, $this->production, 2);
    toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $this->closed->id]);

    $entry = AuditEntry::query()->where('event', 'assistant.results_viewed')->sole();

    expect($entry->metadata)->toEqual(['campaign_id' => $this->closed->id, 'area' => false]);
});

it('carries no answer of any person', function (): void {
    riskAnswers($this->closed, $this->production, 6);

    $json = json_encode(toolCard(GetRiskResults::class, $this->scope, ['campaign_id' => $this->closed->id]), JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('"answers"')->not->toContain('participant')->not->toContain('employee');
});

it('serves a viewer too, who sees results but never the Guía I', function (): void {
    riskAnswers($this->closed, $this->production, 6);
    $viewer = assistantScopeIn($this->a, TenantRole::Viewer);

    $card = toolCard(GetRiskResults::class, $viewer, ['campaign_id' => $this->closed->id]);

    expect($card['rows'])->not->toBeEmpty();
});

it('takes only a campaign and an area from the model, never a company or a person', function (): void {
    $schema = resolve(GetRiskResults::class, ['scope' => $this->scope])->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['campaign_id', 'area_id']);
});
