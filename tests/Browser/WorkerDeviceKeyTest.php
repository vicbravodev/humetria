<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Notification;
use Pest\Browser\Playwright\Playwright;

/*
 * The phone's own key to mark without internet (plan B), prepared from «Cuenta» in the person's portal: the proof of who they
 * are comes first, the key and its pass stay in the phone (IndexedDB), and «Salir» takes them out of the phone without
 * revoking the key at the server.
 */
beforeEach(function (): void {
    // IndexedDB and WebCrypto need a secure origin: localhost is one.
    Playwright::setHost('localhost');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
});

afterEach(function (): void {
    Playwright::setHost(null);
});

/** What the phone keeps for the device key of the company `acme`, as the names of its records in IndexedDB. */
function keptInPhone(): string
{
    return <<<'JS'
        () => new Promise((resolve) => {
            const open = indexedDB.open('humetria-me-acme', 1);
            open.onerror = () => resolve('none');
            open.onsuccess = () => {
                const request = open.result.transaction('kv').objectStore('kv').getAllKeys();
                request.onsuccess = () => resolve(request.result.sort().join(','));
            };
        })
        JS;
}

it('prepares this phone from Cuenta after the proof, keeps the key in the phone and says it is ready', function (bool $dark, bool $mobile): void {
    $page = visitPage('worker-access/home', '/me/acme', $dark, $mobile, enteringTo($this->ana->id, 'Hola, Ana López'))
        ->assertNoAccessibilityIssues(1);

    $page->press('Cuenta')->waitForText('Marcar sin internet con este teléfono')
        ->assertSee('Puede preparar hasta 3 teléfonos')
        ->assertScript(spilledOut(), '')
        ->assertNoAccessibilityIssues(1);

    // The server asks for a recent proof first; once given, the preparing goes by itself.
    $page->press('Preparar este teléfono')->waitForText('Confirme que es usted');
    provedWithCode($page, 'Este teléfono está listo para marcar sin internet')
        ->assertSee('al menos una vez al día')
        ->assertScript(eventuallyAsync('async () => ('.trim(keptInPhone()).')().then((names) => names.includes("device-key") && names.includes("pass"))'), true)
        ->assertScript(settled(), true)
        ->assertNoAccessibilityIssues(1);

    expect(WorkerDeviceKey::query()->withoutGlobalScopes()->where('employee_id', $this->ana->id)->whereNull('revoked_at')->count())->toBe(1);
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

it('«Salir» takes the key and its pass out of the phone and leaves the key standing at the server', function (): void {
    $page = visitPage('worker-access/home', '/me/acme', reach: enteringTo($this->ana->id, 'Hola, Ana López'));
    $page->press('Cuenta')->waitForText('Marcar sin internet con este teléfono')->press('Preparar este teléfono')->waitForText('Confirme que es usted');
    provedWithCode($page, 'Este teléfono está listo para marcar sin internet');

    $page->press('Salir')->waitForText('Entre a su portal');

    $page->assertScript(eventuallyAsync('async () => ('.trim(keptInPhone()).')().then((names) => !names.includes("device-key") && !names.includes("pass"))'), true);
    // What the phone already signed with it is still accepted: leaving revokes nothing.
    expect(WorkerDeviceKey::query()->withoutGlobalScopes()->where('employee_id', $this->ana->id)->whereNull('revoked_at')->count())->toBe(1);
});
