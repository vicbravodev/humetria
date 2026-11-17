<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

/*
 * The Guía I and Programa cards of the results share a row by how many come (docs/design-system/componentes/Card.md):
 * two, side by side on a wide screen; one alone takes the whole width with its content in a single line, so it leaves
 * no hole beside it; on a phone they stack.
 */

/** Each card of the row: whether it is as wide as the row, and whether its title and its button sit on one line. */
const SIDE_CARDS = <<<'JS'
    () => [...document.querySelectorAll('section[aria-labelledby="trauma-heading"], section[aria-labelledby="program-title"]')].map((card) => {
        const middle = (box) => box.top + box.height / 2;
        const title = card.querySelector('h2').getBoundingClientRect();
        const button = card.querySelector('a').getBoundingClientRect();

        return {
            full: Math.abs(card.getBoundingClientRect().width - card.parentElement.getBoundingClientRect().width) < 2,
            oneLine: Math.abs(middle(title) - middle(button)) < 12,
        };
    })
    JS;

it('lays out the Guía I and Programa cards by how many there are', function (TenantRole $role, bool $risk, int $cards, bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $campaign = closedEvaluationWith($tenant, $risk ? nom035Answers('nom035.guide-ii', 'always') : nom035HealthyAnswers('nom035.guide-ii'));
    $this->actingAs(memberOf($tenant, $role));
    $card = ['full' => $mobile || $cards === 1, 'oneLine' => ! $mobile && $cards === 1];

    visitPage('results/show', "/admin/acme/campaigns/{$campaign->id}/results", $dark, $mobile)
        ->assertSee('Calificación final')
        ->assertScript(SIDE_CARDS, array_fill(0, $cards, $card));
})->with([
    'both' => [TenantRole::Hr, true, 2],
    'only the Guía I' => [TenantRole::Hr, false, 1],
    'only the Programa' => [TenantRole::Viewer, true, 1],
    'neither' => [TenantRole::Viewer, false, 0],
])->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);
