<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;

/**
 * Ana, invited to a published climate survey whose company and area (Ventas) have commitments: the address of her page and
 * the steps that take her there through the entrance.
 *
 * @return array{string, Closure}
 */
function climatePortal(): array
{
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    $sales = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ventas']);
    $ana = Employee::factory()->create(['tenant_id' => $tenant->id, 'area_id' => $sales->id, 'name' => 'Ana Solís']);
    workIn($tenant);
    $survey = climateResultsSurvey($tenant, 12);
    $survey->update(['invited' => 20, 'results_published_at' => now()]);
    Invitation::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'employee_id' => $ana->id, 'area_id' => $sales->id]);
    Commitment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'heard' => 'Que el buen trabajo no se reconoce.', 'will_do' => 'Reconocer por escrito cada logro del mes.', 'owner_name' => 'Ana Torres']);
    Commitment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'group_type' => 'area', 'group_id' => $sales->id, 'will_do' => 'Revisar las metas de Ventas cada mes.']);

    return ['/me/acme/climate', enteringTo($ana->id, 'Lo que dijeron y lo que haremos')];
}

it('shows the person what the company heard and will do, in their own frame, on a phone and a desktop', function (bool $dark, bool $mobile): void {
    [$url, $enter] = climatePortal();

    visitPage('engagement/portal-climate', $url, $dark, $mobile, $enter)
        ->assertSee('Lo que dijeron y lo que haremos')
        ->assertSee('Autopartes Demo')
        ->assertSee('Personas que contestaron')
        ->assertSee('60 %')
        ->assertSee('Nos dijeron')
        ->assertSee('Haremos')
        ->assertSee('Reconocer por escrito cada logro del mes.')
        ->assertSee('Revisar las metas de Ventas cada mes.')
        ->assertSee('Toda la empresa')
        ->assertSee('Su área')
        ->assertNotPresent('[data-sidebar="sidebar"]')
        // Hallazgo 33: «Personal» with a lock said nothing on a page of group results; the header carries only the company.
        ->assertDontSeeIn('header[class]', 'Personal')
        ->assertDontSeeIn('header[class]', 'Confidencial')
        ->assertDontSee('Net Promoter');
})->with([
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
]);
