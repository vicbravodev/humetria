<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

dataset('error looks', [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('shows its own page for an address that does not exist', function (bool $dark, bool $mobile): void {
    visitPage('error', '/no-such-page', $dark, $mobile)
        ->assertSee('Error 404')
        ->assertSee('No encontramos esta página')
        ->assertSee('Ir al inicio');
})->with('error looks');

it('tells a person which role keeps them out of a section, and takes them back to the panel', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo']);
    $this->actingAs(memberOf($tenant, TenantRole::Viewer));

    visitPage('error', route('tenant.employees.index', $tenant, absolute: false), $dark, $mobile)
        ->assertSee('Error 403')
        ->assertSee('Esta sección no está en tu rol')
        ->assertSee('Tu rol en Autopartes Demo es Solo consulta, que ve resultados e informes.')
        ->click('Volver al panel')
        ->assertPathBeginsWith('/admin');
})->with('error looks');

it('asks to wait a minute after too many attempts, on the app own page', function (bool $dark, bool $mobile): void {
    Route::middleware(['web', 'throttle:1,1'])->get('/test/limited', fn () => 'ok');
    $this->get('/test/limited')->assertOk();

    visitPage('error', '/test/limited', $dark, $mobile)
        ->assertSee('Error 429')
        ->assertSee('Demasiados intentos')
        ->assertSee('Espera un minuto y vuelve a intentarlo.')
        ->assertSee('Ir al inicio');
})->with('error looks');

it('speaks "usted" on the survey and offers to open the link again, not the landing', function (bool $dark, bool $mobile): void {
    foreach (range(1, 30) as $attempt) {
        $this->get('/survey/the-token')->assertOk();
    }

    visitPage('error', '/survey/the-token', $dark, $mobile)
        ->assertSee('Demasiados intentos')
        ->assertSee('Espere un minuto y vuelva a intentarlo.')
        ->assertDontSee('Ir al inicio')
        ->assertPresent('a[href$="/survey/the-token"]')
        ->assertSee('Volver a intentar');
})->with('error looks');

it('gives the request id to quote to support on a server error', function (bool $dark, bool $mobile): void {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/test/fails', fn () => abort(500));

    visitPage('error', '/test/fails', $dark, $mobile)
        ->assertSee('Algo salió mal de nuestro lado')
        ->assertSee('Código para soporte')
        ->assertScript('() => /^[0-9a-f-]{36}$/.test(document.querySelector("[data-slot=request-id] code")?.textContent ?? "")', true);
})->with('error looks');

it('says nothing of a request id on an error of the person, not of the server', function (): void {
    visitPage('error', '/no-such-page')->assertDontSee('Código para soporte');
});

it('shows a calm page, instead of a blank one, when a page breaks while it renders', function (bool $dark, bool $mobile): void {
    // Only here: a page handed props it cannot render (an object where it writes the company's name) breaks inside React.
    Route::middleware('web')->get('/test/broken-page', fn () => Inertia::render('error', [
        'status' => 403,
        'survey' => false,
        'tenant' => ['trade_name' => ['broken' => true], 'role' => 'viewer', 'role_label' => 'Solo consulta'],
    ]));

    $pending = $mobile ? visit('/test/broken-page')->on()->mobile() : visit('/test/broken-page');
    $page = $dark ? $pending->inDarkMode() : $pending->inLightMode();

    accessible($page->assertSee('Algo salió mal')
        ->assertSee('Recargar la página')
        ->assertScript(cspViolations(), '[]'), 1)
        ->click('Recargar la página')
        ->assertSee('Algo salió mal');
})->with('error looks');
