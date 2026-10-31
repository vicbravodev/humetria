<?php

declare(strict_types=1);

use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the NOM-037 settings with the default period and the base consent', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('telework/settings', "{$url}/telework/settings", $dark, $mobile)
        ->assertSee('Ajustes de NOM-037')
        ->assertSee('Versión 1')
        ->assertScript('document.querySelector("input[type=radio]:checked")?.parentElement?.textContent', '12 meses');
})->with($looks);

it('saves a custom period and a new consent text', function (): void {
    $url = signInToPanel();

    visit("{$url}/telework/settings")
        ->click('Otro')
        ->type('#period-custom', '18')
        ->type('consent_text', 'Texto propio de la empresa.')
        ->press('Guardar ajustes')
        ->assertSee('Ajustes de NOM-037 guardados.')
        ->assertSee('Versión 2')
        ->assertValue('#period-custom', '18')
        ->assertNoJavaScriptErrors();
});

/** @return array{string, WorkCenter} The panel's address and a work center of the company the user signed in to. */
function committeeCenter(): array
{
    $url = signInToPanel();

    return [$url, WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create(['name' => 'Planta Querétaro'])];
}

it('shows the Comisión of a center before it is registered', function (bool $dark, bool $mobile): void {
    [$url, $center] = committeeCenter();

    visitPage('telework/committee', "{$url}/telework/centers/{$center->id}/committee", $dark, $mobile)
        ->assertSee('Comisión de seguridad e higiene')
        ->assertSee('Fecha del acta')
        ->assertSee('Registrar Comisión');
})->with($looks);

it('shows the Comisión of a center with its people and with none', function (bool $dark, bool $mobile): void {
    [$url, $center] = committeeCenter();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $cora = memberOf($tenant, TenantRole::Committee);
    SafetyCommittee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    CommitteeAssignment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'user_id' => $cora->id]);

    visitPage('telework/committee', "{$url}/telework/centers/{$center->id}/committee", $dark, $mobile)
        ->assertSee($cora->name)
        ->assertSee('1 integrante');
})->with($looks);

it('registers the Comisión from the page and then lets the last member go', function (): void {
    [$url, $center] = committeeCenter();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $cora = memberOf($tenant, TenantRole::Committee);

    visit("{$url}/telework/centers/{$center->id}/committee")
        ->click('#committee-constituted-on')
        // A week before the day the calendar opens on: the browser's today may be a day ahead of the server's.
        ->keys('[data-day] button[tabindex="0"]', ['ArrowUp', 'Enter'])
        ->click("#member-{$cora->id}")
        ->press('Registrar Comisión')
        ->assertSee('Comisión registrada')
        ->assertSee($cora->name)
        ->press('Quitar')
        ->assertSee('La Comisión no tiene integrantes')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});
