<?php

declare(strict_types=1);

use App\Modules\Assistant\Support\ToolRegistry;
use App\Modules\Assistant\Tools\OfferChoices;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;

/** A company with a center, a closed evaluation with results and a running one: everything a tool may name or lead to. */
function linkedCompany(string $center): array
{
    $tenant = Tenant::factory()->create();
    $plant = WorkCenter::factory()->create(['tenant_id' => $tenant->id, 'name' => $center]);
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'workers' => 30]);
    $running = Campaign::factory()->active(now()->addDays(3)->toDateString())->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
    AnonymousResponse::factory()->count(6)->create(['campaign_id' => $closed->id, 'area_id' => Area::factory()->create(['tenant_id' => $tenant->id])->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', 'never')]);

    return [$tenant, $closed, $running];
}

it('never names a source nor leads to a page of another company, whatever id the model sends', function (): void {
    config(['first_steps.enabled' => true]);
    [$b, $closedB, $runningB] = linkedCompany('Planta de la otra');
    [$a, $closedA, $runningA] = linkedCompany('Planta Apodaca');
    $scope = assistantScopeIn($a, TenantRole::Owner);
    $arguments = [[], ['campaign_id' => $closedA->id], ['campaign_id' => $runningA->id], ['campaign_id' => $closedB->id], ['campaign_id' => $runningB->id], ['query' => 'mínimo por grupo']];
    $named = [];

    // OfferChoices reads nothing of the company: it only draws the buttons the model wrote, with no source and no next page.
    foreach (array_filter(resolve(ToolRegistry::class)->for($scope), fn (Tool $tool): bool => ! $tool instanceof OfferChoices) as $tool) {
        foreach ($arguments as $argument) {
            $card = json_decode((string) $tool->handle(new ToolRequest($argument)), true, flags: JSON_THROW_ON_ERROR);
            $named[] = json_encode([$card['sources'], $card['next']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            expect($card['next'] === null || str_starts_with($card['next']['link'], "/admin/{$a->slug}"))->toBeTrue();
        }
    }

    $all = implode("\n", $named);

    expect($all)->not->toContain($b->slug)
        ->not->toContain($closedB->id)
        ->not->toContain($runningB->id)
        ->not->toContain('Planta de la otra')
        ->toContain($closedA->id)
        ->toContain('Planta Apodaca');
});
