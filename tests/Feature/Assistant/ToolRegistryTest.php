<?php

declare(strict_types=1);

use App\Modules\Assistant\Support\ToolRegistry;
use App\Modules\Assistant\Tools\GetCampaignProgress;
use App\Modules\Assistant\Tools\GetComplianceOverview;
use App\Modules\Assistant\Tools\GetRiskResults;
use App\Modules\Assistant\Tools\ListPendingActions;
use App\Modules\Assistant\Tools\SearchRegulations;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;

mutates(ToolRegistry::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->scope = assistantScopeIn($this->a);
});

it('gives the five tools of Humi for a scope', function (): void {
    $tools = resolve(ToolRegistry::class)->for($this->scope);

    expect(array_map(fn (Tool $tool): string => $tool::class, $tools))->toBe([
        GetComplianceOverview::class,
        GetCampaignProgress::class,
        GetRiskResults::class,
        ListPendingActions::class,
        SearchRegulations::class,
    ]);
});

it('adds a tool of a new module by listing its class, nothing else', function (): void {
    $registry = new ToolRegistry(app(), [SearchRegulations::class]);

    expect(array_map(fn (Tool $tool): string => $tool::class, $registry->for($this->scope)))->toBe([SearchRegulations::class]);
});

it('lets no tool take a company, a tenant, a person or a participant from the model', function (): void {
    foreach (resolve(ToolRegistry::class)->for($this->scope) as $tool) {
        foreach (array_keys($tool->schema(new JsonSchemaTypeFactory)) as $field) {
            expect($field)->not->toMatch('/tenant|company|empresa|user|usuario|member|miembro|participant|team|account|cuenta/i');
        }

        expect((string) $tool->description())->not->toBeEmpty();
    }
});

it('never lets a tool of a company reach the data of another, even asking for its ids by name', function (): void {
    $plantB = WorkCenter::factory()->create(['tenant_id' => $this->b->id, 'name' => 'Planta Secreta de B']);
    $areaB = Area::factory()->create(['tenant_id' => $this->b->id, 'name' => 'Área Secreta de B']);
    $campaignB = Campaign::factory()->closed()->create(['tenant_id' => $this->b->id, 'work_center_id' => $plantB->id]);
    AnonymousResponse::factory()->count(8)->create(['campaign_id' => $campaignB->id, 'area_id' => $areaB->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', 'never')]);
    workIn($this->a);

    foreach (resolve(ToolRegistry::class)->for($this->scope) as $tool) {
        $answer = (string) $tool->handle(new ToolRequest(['campaign_id' => $campaignB->id, 'area_id' => $areaB->id, 'query' => 'Planta Secreta de B', 'tenant_id' => $this->b->id]));

        expect($answer)->not->toContain('Secreta')->not->toContain($campaignB->id)->not->toContain($areaB->id)->not->toContain($this->b->id);
    }
});
