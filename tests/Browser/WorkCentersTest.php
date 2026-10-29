<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Pest\Browser\Api\AwaitableWebpage;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('invites a company without work centers to add the first one', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('tenancy/work-centers', "{$url}/work-centers", $dark, $mobile)
        ->assertSee('Aún no hay centros de trabajo')
        ->assertDontSee('Archivados');
})->with($looks);

it('lists the work centers with their address, and the archived ones apart', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro', 'street' => 'Av. 5 de Febrero', 'exterior_number' => '1200', 'interior_number' => null, 'neighborhood' => 'Zona Industrial', 'postal_code' => '76120', 'municipality' => 'Querétaro', 'state' => MexicanState::Queretaro]);
    WorkCenter::factory()->for($tenant)->archived()->create(['name' => 'Bodega Vieja']);

    visitPage('tenancy/work-centers', "{$url}/work-centers", $dark, $mobile)
        ->assertSee('Planta Querétaro')
        ->assertSee('Av. 5 de Febrero 1200, col. Zona Industrial, C.P. 76120, Querétaro, Querétaro')
        ->assertDontSee('Bodega Vieja');

    visitPage('tenancy/work-centers', "{$url}/work-centers", $dark, $mobile, fn (AwaitableWebpage $page) => $page
        ->click('Archivados 1')
        ->assertPathIs('/admin/acme/work-centers'))
        ->assertSee('Bodega Vieja')
        ->assertDontSee('Planta Querétaro');
})->with($looks);

it('reaches the work centers from the sidebar', function (): void {
    $url = signInToPanel();

    visit($url)
        ->click('Centros de trabajo')
        ->assertPathIs('/admin/acme/work-centers')
        ->assertSee('Aún no hay centros de trabajo');
});

function acme(): Tenant
{
    return Tenant::query()->where('slug', 'acme')->sole();
}

it('adds a work center from the dialog, choosing its state from the list', function (): void {
    $url = signInToPanel();

    visit("{$url}/work-centers")
        ->click('Dar de alta centro')
        ->type('name', 'Planta Querétaro')
        ->type('street', 'Av. 5 de Febrero')
        ->type('exterior_number', '1200')
        ->type('neighborhood', 'Zona Industrial')
        ->type('postal_code', '76120')
        ->type('municipality', 'Querétaro')
        ->click('#work-center-state')
        ->click('[role="option"]:has-text("Querétaro")')
        ->type('main_activity', 'Manufactura de autopartes')
        ->press('Dar de alta')
        ->assertSee('Centro de trabajo dado de alta.')
        ->assertSee('Av. 5 de Febrero 1200, col. Zona Industrial, C.P. 76120, Querétaro, Querétaro')
        ->assertNoJavaScriptErrors();

    workIn(acme());

    expect(WorkCenter::query()->sole()->state)->toBe(MexicanState::Queretaro);
});

it('shows what is wrong in the dialog and keeps it open', function (): void {
    $url = signInToPanel();
    WorkCenter::factory()->for(acme())->create(['name' => 'Planta Querétaro']);

    visit("{$url}/work-centers")
        ->click('Dar de alta centro')
        ->type('name', 'planta querétaro')
        ->type('postal_code', '761')
        ->press('Dar de alta')
        ->assertSee('Ya hay un centro de trabajo con este nombre.')
        ->assertSee('Escribe un código postal de 5 dígitos.')
        ->assertSee('El campo estado es obligatorio.')
        ->assertNoJavaScriptErrors();
});

it('passes the accessibility and CSP checks with the dialog open', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('tenancy/work-centers', "{$url}/work-centers", $dark, $mobile, fn (AwaitableWebpage $page) => $page
        ->click('Dar de alta centro')
        ->assertSee('Actividad principal'));
})->with($looks);

it('edits a work center from its row', function (): void {
    $url = signInToPanel();
    WorkCenter::factory()->for(acme())->create(['name' => 'Planta Querétaro', 'state' => MexicanState::Queretaro]);

    visit("{$url}/work-centers")
        ->click('@work-center-actions')->click('Editar')
        ->assertSee('Editar centro de trabajo')
        ->assertValue('name', 'Planta Querétaro')
        ->assertSeeIn('#work-center-state', 'Querétaro')
        ->type('name', 'Planta El Marqués')
        ->press('Guardar')
        ->assertSee('Centro de trabajo actualizado.')
        ->assertSee('Planta El Marqués')
        ->assertNoJavaScriptErrors();
});

it('passes the accessibility and CSP checks with the edit dialog and the menu open', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    WorkCenter::factory()->for(acme())->create();

    visitPage('tenancy/work-centers', "{$url}/work-centers", $dark, $mobile, fn (AwaitableWebpage $page) => $page
        ->click('@work-center-actions')->click('Editar')
        ->assertSee('Editar centro de trabajo'));
})->with($looks);

it('archives a work center after confirming, and restores it from the archive', function (): void {
    $url = signInToPanel();
    WorkCenter::factory()->for(acme())->create(['name' => 'Bodega Norte']);

    visit("{$url}/work-centers")
        ->click('@work-center-actions')->click('Archivar')
        ->assertSee('¿Archivar Bodega Norte?')
        ->press('Archivar')
        ->assertSee('Centro de trabajo archivado.')
        ->assertSee('Aún no hay centros de trabajo')
        ->click('Archivados 1')
        ->assertSee('Bodega Norte')
        ->click('@work-center-actions')->click('Restaurar')
        ->assertSee('Centro de trabajo restaurado.')
        ->assertSee('Bodega Norte')
        ->assertDontSee('Archivados')
        ->assertNoJavaScriptErrors();
});
