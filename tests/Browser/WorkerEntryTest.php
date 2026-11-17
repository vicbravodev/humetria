<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Notification;

/*
 * The person's own pages, with no account: the screen to enter, a one-time link and their start.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
});

/** A one-time link of Ana that still opens, of the kind given. */
function anaLink(string $token, bool $requested = false): void
{
    $factory = WorkerAccessLink::factory()->withToken($token);
    ($requested ? $factory->requested() : $factory)->create(['tenant_id' => test()->tenant->id, 'employee_id' => test()->ana->id]);
}

it('opens the screen to enter, naming nobody, with the key and the link by email', function (bool $dark, bool $mobile): void {
    visitPage('worker-access/enter', '/me/acme', $dark, $mobile)
        ->assertSee('Entre a su portal')
        ->assertSee('Entrar con mi huella')
        ->assertSee('Entrar con un enlace por correo')
        ->assertDontSee('Ana López')
        ->assertNoAccessibilityIssues(1);
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
]);

it('answers the same to an email asked for, whoever it is', function (): void {
    visitPage('worker-access/enter', '/me/acme')
        ->type('input[type=email]', 'nadie@acme.mx')
        ->press('Enviarme un enlace')
        ->assertSee('Si ese correo es de una persona de la empresa');
});

it('opens a link with «Continuar», and its start names the person', function (bool $dark, bool $mobile): void {
    anaLink('browser-token', requested: true);

    $page = visitPage('worker-access/link', '/me/acme/link/browser-token', $dark, $mobile)
        ->assertSee('Su enlace está listo')
        ->assertNoAccessibilityIssues(1);

    $page->press('Continuar')->waitForText('Hola, Ana López')->assertSee('Cuenta');
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

it('shows the start with the session open, its three tabs, and «Salir» in Cuenta leaves no name behind', function (bool $dark, bool $mobile): void {
    anaLink('home-token', requested: true);

    $page = visitPage('worker-access/home', '/me/acme/link/home-token', $dark, $mobile, fn ($page) => $page->press('Continuar')->waitForText('Hola, Ana López'))
        ->assertSeeIn('nav[aria-label="Secciones del portal"]', 'Inicio')
        ->assertSeeIn('nav[aria-label="Secciones del portal"]', 'Pendientes')
        ->assertDontSee('Salir')
        ->assertScript(spilledOut(), '')
        ->assertNoAccessibilityIssues(1);

    $page->press('Pendientes')->waitForText('Está al día')->assertScript(settled(), true)->assertNoAccessibilityIssues(1);
    $page->press('Cuenta')->waitForText('Su cuenta')->assertSee('Ana López')->assertScript(settled(), true)->assertNoAccessibilityIssues(1);
    $page->press('Salir')->waitForText('Entre a su portal')->assertDontSee('Ana López');
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
]);

it('shows one screen for a link that does not work', function (): void {
    visitPage('worker-access/link', '/me/acme/link/unknown')
        ->assertSee('Este enlace ya no sirve')
        ->assertSee('Pedir un enlace nuevo')
        ->assertNoAccessibilityIssues(1);
});

it('opens RH\'s link straight into creating a key, with the warning on shared accounts', function (bool $dark, bool $mobile): void {
    anaLink('issued-token');

    visitPage('worker-access/key', '/me/acme/link/issued-token', $dark, $mobile, fn ($page) => $page->press('Continuar')->waitForText('cree su llave de acceso'))
        ->assertSee('Ana López, cree su llave de acceso')
        ->assertSee('Si comparte su cuenta de iCloud o de Google con su familia')
        ->assertSee('Crear llave')
        ->assertNoAccessibilityIssues(1);
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
]);

it('asks before «No fui yo» revokes a key, then says it is done', function (bool $dark, bool $mobile): void {
    $key = App\Modules\WorkerAccess\Models\WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone']);
    $url = Illuminate\Support\Facades\URL::temporarySignedRoute('worker.not-me.show', now()->addDays(30), ['tenant' => 'acme', 'credential' => $key->id], absolute: false);

    $page = visitPage('worker-access/not-me', $url, $dark, $mobile)
        ->assertSee('¿No creó usted la llave «iPhone»?')
        ->assertNoAccessibilityIssues(1);

    $page->press('Anular esa llave')->waitForText('La llave quedó anulada');
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);
