<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\WorkCenter;

/*
 * The cards with one bar per group (Por dominio, Por categoría, Por área, Participación por área) read top to bottom
 * (docs/design-system/componentes/Card.md): the heading above, the bars as wide as the card, the action at the foot.
 * No side column for the heading alone, which left most of a third of the card empty.
 */

/**
 * The card around the heading named `$title`: whether its heading sits above the bars, the bars take the card's whole
 * width, and its action (its last link, button or summary) comes after them.
 */
function stacksCard(string $title): string
{
    $title = json_encode($title, JSON_THROW_ON_ERROR);

    return <<<JS
        () => {
            const heading = [...document.querySelectorAll('h2')].find((each) => each.textContent.trim() === {$title});
            const card = heading.closest('section');
            const bars = card.querySelector('ol, ul');
            const actions = card.querySelectorAll('summary, button');
            const action = actions[actions.length - 1].getBoundingClientRect();
            const style = getComputedStyle(card);
            const inner = card.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            const list = bars.getBoundingClientRect();

            return {
                headingAbove: heading.getBoundingClientRect().bottom <= list.top,
                fullWidth: Math.abs(list.width - inner) < 2,
                actionAtTheFoot: action.top >= list.bottom,
            };
        }
        JS;
}

$stacked = ['headingAbove' => true, 'fullWidth' => true, 'actionAtTheFoot' => true];

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('stacks the domains, the categories and the areas of the results', function (bool $dark, bool $mobile) use ($stacked): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    [$campaign] = closedEvaluationInAreas($tenant, ['Producción' => 6, 'Ventas' => 5]);
    $this->actingAs(memberOf($tenant, TenantRole::Hr));

    visitPage('results/show', "/admin/acme/campaigns/{$campaign->id}/results", $dark, $mobile)
        ->assertScript(stacksCard('Por dominio'), $stacked)
        ->assertScript(stacksCard('Por categoría'), $stacked)
        ->assertScript(stacksCard('Por área'), $stacked);
})->with($looks);

it('stacks the participation by area of a running evaluation', function (bool $dark, bool $mobile) use ($stacked): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    TenantMembership::factory()->for($tenant)->for($user)->role(TenantRole::Hr)->create();
    $this->actingAs($user);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    staffOf($tenant, $plant, 20);
    Illuminate\Support\Facades\Notification::fake();
    $this->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id, 'population' => 'all']);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();
    $this->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => now()->addWeek()->toDateString()]);

    visitPage('campaigns/show', "/admin/acme/campaigns/{$campaign->id}", $dark, $mobile)
        ->assertSee('Participación por área')
        ->assertScript(stacksCard('Participación por área'), $stacked);
})->with($looks);
