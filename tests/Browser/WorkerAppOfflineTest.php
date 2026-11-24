<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceOfflineMark;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Notification;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Playwright;

/*
 * The installed app of a company: its shell and its service worker. The cold start with no network at all (the worker
 * answering the shell) is checked in real phones, not here: these tests cover what can be seen with the network up.
 */

beforeEach(function (): void {
    // Service workers need a secure origin: localhost is one, an address like 127.0.0.1 is not for the browser's checks.
    Playwright::setHost('localhost');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    // A 50 m zone around 19.4326, -99.1332.
    AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López']);
});

afterEach(function (): void {
    Playwright::setHost(null);
});

/** A condition on the cache of the company `acme`, whatever its revision. */
function acmeCache(string $then): string
{
    return "async () => {
        const name = (await caches.keys()).find((key) => key.startsWith('humetria-pwa-acme-'));
        if (name === undefined) { return false; }
        const urls = (await (await caches.open(name)).keys()).map((request) => new URL(request.url).pathname);
        return {$then};
    }";
}

it('opens the shell healthy, light and dark, on a desktop and on a phone', function (bool $dark, bool $mobile): void {
    visitShell('/me/acme/offline', $dark, $mobile)
        ->assertSee('No hay internet')
        ->assertSee('Reintentar')
        ->assertPresent('a[href="/me/acme"]');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('puts a worker in charge of the company\'s pages that keeps the shell and its files', function (): void {
    $page = visit('/me/acme/punch')->assertNoJavaScriptErrors();

    $page->assertScript(workerReady(), true)
        ->assertScript(eventuallyAsync(acmeCache("urls.includes('/me/acme/offline') && urls.some((url) => url.startsWith('/build/assets/'))")), true)
        ->assertScript(eventuallyAsync("async () => (await navigator.serviceWorker.getRegistration('/me/acme'))?.scope === location.origin + '/me/acme'"), true);
});

it('keeps answering what the server says: a 404 with the network up is still a 404, never the shell', function (): void {
    $page = visit('/me/acme/punch');
    $page->assertScript(workerReady(), true);

    $page->navigate('/me/acme/no-existe')
        ->assertScript(eventually('() => performance.getEntriesByType("navigation")[0].responseStatus === 404'), true)
        ->assertScript('() => document.querySelector("#offline-app") === null', true);
});

it('switched off, takes the worker and the caches out of the phone and does not come back', function (): void {
    $page = visit('/me/acme/punch');
    $page->assertScript(workerReady(), true)
        ->assertScript(eventuallyAsync(acmeCache('true')), true);

    config(['worker_access.pwa_sw_kill' => true]);

    $page->navigate('/me/acme/punch')
        ->assertScript(eventuallyAsync('async () => (await navigator.serviceWorker.getRegistrations()).length === 0'), true)
        ->assertScript(eventuallyAsync("async () => !(await caches.keys()).some((key) => key.startsWith('humetria-pwa-acme-'))"), true);

    // Another visit does not register it again.
    $page->navigate('/me/acme')
        ->assertScript('() => new Promise((resolve) => setTimeout(resolve, 1500)).then(() => navigator.serviceWorker.getRegistrations()).then((registrations) => registrations.length)', 0);
});

/**
 * Ana's phone, linked with a fingerprint reader, after her first mark with the network: it brings the tickets and the context
 * the phone keeps for the day the signal is gone. The page it gives is Marcar opened with no code in its address, so what comes
 * next is a mark without a QR.
 */
function phoneWithTickets(): AwaitableWebpage
{
    $page = visitPage('worker-access/link', accessLinkPath(test()->ana->id), mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    linkThePhone($page, test()->ana->id);
    $page->navigate('/me/acme/punch?q='.QrToken::issue(test()->tenant->id, test()->plant->id, CarbonImmutable::now()))->waitForText('Marcar su jornada');
    standingAt($page, 19.4327, -99.1332);
    $page->press('button:has-text("Entrada")')->waitForText('Entrada registrada')->press('Listo');
    $page->assertScript(eventuallyAsync('async () => ('.trim(keptInPhone()).')().then((names) => names.includes("tickets") && names.includes("context"))'), true);

    return $page->navigate('/me/acme/punch')->waitForText('Salir a comer');
}

/** A mark of Ana's made without the network: the sheet asks the hour, the fingerprint reader signs, the mark waits in the phone. */
function markOffline(AwaitableWebpage $page, string $button, string $sheet): void
{
    $page->press("button:has-text(\"{$button}\")")->waitForText($sheet)->press('button:has-text("Guardar con su huella")')->waitForText('Guardada sin conexión');
}

it('marks without a connection, keeps the mark through a reload and delivers it by itself when the network is back', function (bool $dark): void {
    $page = phoneWithTickets();
    // The online mark that brought the tickets already remembered a first mark: only the offline one counts here.
    $page->script("() => localStorage.removeItem('humetria-push-marked')");
    withoutNetwork($page);

    markOffline($page, 'Salir a comer', 'Salida a comer sin internet');
    $page->assertSee('Usted indicó')
        ->assertSee('Aún no la recibe Recursos Humanos')
        ->assertSee('1 marca sin enviar')
        ->assertSee('Si borra los datos de la app antes de que se envíe, esta marca se pierde.')
        ->assertDontSee('Entrada registrada')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1)
        // A mark saved without a connection is the person's first mark too: the start may offer the notices from now on.
        ->assertScript("() => localStorage.getItem('humetria-push-marked')", '1');

    // The mark is in the phone, signed, and nothing reached the server.
    expect(AttendanceMark::query()->count())->toBe(1)->and(AttendanceOfflineMark::query()->count())->toBe(0);

    // A reload with the network still down: the count is where it was left, in IndexedDB.
    $page->navigate('/me/acme/punch')->waitForText('1 marca sin enviar')->assertSee('Abra Humetría con internet para enviar su marca.');

    withoutNetwork($page, false);
    $page->assertScript(eventually('() => document.body.innerText.includes("Recibida. Cuenta en sus horas. Recursos Humanos la revisará.")'), true)
        ->assertDontSee('1 marca sin enviar')
        ->assertNoAccessibilityIssues(1);

    $row = AttendanceOfflineMark::query()->sole();
    expect($row->outcome)->toBe('accepted')->and($row->mark_type)->toBe(MarkType::BreakStart)->and($row->mark?->method)->toBe(MarkMethod::OfflineDeclared)
        ->and(AttendanceMark::query()->count())->toBe(2);
    $page->assertScript(eventuallyAsync('async () => ('.trim(keptInPhone()).')().then((names) => !names.includes("mark/1"))'), true);
})->with(['light' => [false]]);

it('shows a mark the server did not accept until the person says «Entendido», also after a reload', function (): void {
    $page = phoneWithTickets();
    withoutNetwork($page);
    markOffline($page, 'Salir a comer', 'Salida a comer sin internet');
    // Ana changed centers while she had no signal: the mark names the old one.
    Employee::query()->whereKey($this->ana->id)->update(['work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]);

    withoutNetwork($page, false);
    $page->navigate('/me/acme/punch')->waitForText('No aceptada: el centro no es el suyo. Puede pedir una corrección.')
        ->assertSee('Usted indicó')
        ->assertSee('Entendido')
        ->assertSee('Pedir corrección')
        ->assertDontSee('1 marca sin enviar')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);

    expect(AttendanceOfflineMark::query()->sole()->outcome)->toBe('rejected')->and(AttendanceMark::query()->count())->toBe(1);

    // It stays after another reload: it is in the phone until Ana understands.
    $page->navigate('/me/acme/punch')->waitForText('No aceptada: el centro no es el suyo.')
        ->press('Entendido')
        ->assertDontSee('No aceptada: el centro no es el suyo.');
    $page->assertScript(eventuallyAsync('async () => ('.trim(keptInPhone()).')().then((names) => !names.includes("mark/1"))'), true);
});

it('does not lose a mark to a server that fails, and sends it when the server answers', function (): void {
    $page = phoneWithTickets();
    withoutNetwork($page);
    markOffline($page, 'Salir a comer', 'Salida a comer sin internet');

    // The network is back but the server answers the delivery with a 500: the mark stays where it was.
    withoutNetwork($page, false, 500);
    $page->press('Listo')->waitForText('1 marca sin enviar');
    $page->assertScript('() => new Promise((resolve) => setTimeout(resolve, 1500)).then(() => document.body.innerText.includes("1 marca sin enviar"))', true);
    expect(AttendanceOfflineMark::query()->count())->toBe(0);

    // The server answers again: the wait of the first failure (5 s) ends and the mark goes.
    withoutNetwork($page, false);
    $page->assertScript(eventually('() => document.body.innerText.includes("Recibida.")'), true);
    expect(AttendanceOfflineMark::query()->sole()->outcome)->toBe('accepted');
});

it('keeps only the mark the server cannot read as not accepted, and delivers the others', function (): void {
    $page = phoneWithTickets();
    withoutNetwork($page);
    markOffline($page, 'Salir a comer', 'Salida a comer sin internet');
    $page->press('Listo');
    markOffline($page, 'Marcar salida', 'Salida sin internet');
    $page->press('Listo')->assertSee('2 marcas sin enviar');
    damageMark($page, 1);

    withoutNetwork($page, false);
    $page->assertScript(eventually('() => document.body.innerText.includes("la marca se guardó dañada en el teléfono")'), true)
        ->assertSee('No aceptada')
        ->assertSee('Recibida.')
        ->assertNoAccessibilityIssues(1);

    // Only the good one reached the server: the damaged one has no row there, so «Entendido» asks first.
    $row = AttendanceOfflineMark::query()->sole();
    expect($row->mark_type)->toBe(MarkType::End)->and($row->outcome)->toBe('accepted');
    $page->press('Entendido')->assertSee('Recursos Humanos no tiene esta marca. Si la quita, se pierde.')->assertSee('Quitarla');
    $page->press('Quitarla')->assertDontSee('la marca se guardó dañada en el teléfono');
});

it('«¿No es usted?» takes the tickets out of the phone but not the mark that waits, which is delivered all the same', function (): void {
    $page = phoneWithTickets();
    withoutNetwork($page);
    markOffline($page, 'Salir a comer', 'Salida a comer sin internet');
    $page->press('Listo')->assertSee('1 marca sin enviar');

    $page->press('¿No es usted?');
    $page->assertScript(eventuallyAsync('async () => ('.trim(keptInPhone()).')().then((names) => !names.includes("tickets") && !names.includes("context") && names.includes("mark/1"))'), true)
        // The next person to open it sees no count that is not theirs.
        ->assertDontSee('1 marca sin enviar');

    withoutNetwork($page, false);
    $page->assertScript(eventuallyAsync('async () => ('.trim(keptInPhone()).')().then((names) => !names.includes("mark/1"))'), true);
    expect(AttendanceOfflineMark::query()->sole()->outcome)->toBe('accepted');
});

it('sends one mark when the button is tapped twice before the screen changes', function (): void {
    $page = visitPage('worker-access/link', accessLinkPath($this->ana->id), mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    linkThePhone($page, $this->ana->id);
    $page->navigate('/me/acme/punch?q='.QrToken::issue($this->tenant->id, $this->plant->id, CarbonImmutable::now()))->waitForText('Marcar su jornada');
    standingAt($page, 19.4327, -99.1332);

    $page->script('() => { const button = [...document.querySelectorAll("button")].find((b) => b.textContent.trim() === "Entrada"); button.click(); button.click(); }');
    $page->waitForText('Entrada registrada');

    expect(AttendanceMark::query()->count())->toBe(1);
});

it('makes one mark without a connection when the button is tapped twice before the screen changes', function (): void {
    $page = phoneWithTickets();
    $page->navigate('/me/acme/punch?q='.QrToken::issue($this->tenant->id, $this->plant->id, CarbonImmutable::now()))->waitForText('Salir a comer');
    withoutNetwork($page);

    // With a code read there is no sheet: the first tap signs at once, and the second finds it under way.
    $page->script('() => { const button = [...document.querySelectorAll("button")].find((b) => b.textContent.trim() === "Salir a comer"); button.click(); button.click(); }');

    $page->waitForText('Guardada sin conexión')->assertSee('1 marca sin enviar');
    $page->assertScript(eventuallyAsync('async () => ('.trim(keptInPhone()).')().then((names) => names.includes("mark/1") && !names.includes("mark/2"))'), true);
});

it('says how ready the phone is: few tickets, none, and ready', function (): void {
    $page = phoneWithTickets();

    $page->press('¿Puedo marcar sin internet?')->waitForText('Listo para marcar sin internet')
        ->assertSee('Su empresa activó marcar sin internet')
        ->assertSee('Ya aceptó el acuerdo de registro')
        ->assertNoAccessibilityIssues(1);
    $page->press('Volver');

    keepTickets($page, 2);
    $page->navigate('/me/acme/punch')->assertSee('Pocos boletos: abra Humetría con internet para recibir más.');

    keepTickets($page, 0);
    withoutNetwork($page);
    $page->navigate('/me/acme/punch')->press('¿Puedo marcar sin internet?')->waitForText('Aún no está listo: ábrala con internet')
        ->assertSee('Abra Humetría con internet')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);
});

it('without a ticket, the mark without a connection says what is missing instead of failing in silence', function (): void {
    $page = phoneWithTickets();
    keepTickets($page, 0);
    $page->navigate('/me/acme/punch');
    withoutNetwork($page);

    $page->press('button:has-text("Salir a comer")')->waitForText('Aún no está listo: ábrala con internet');
    expect(AttendanceOfflineMark::query()->count())->toBe(0);
});

it('marks from the shell of the installed app, with no page of the server: the same queue, the same delivery', function (bool $dark): void {
    $page = phoneWithTickets();
    $page->navigate('/me/acme/offline')->waitForText('No hay internet');
    withoutNetwork($page);
    // The worker serves the shell at the address of Marcar when the phone opens the icon with no network at all: here the
    // history moves to that address, which is what the shell reads to know it is Marcar it must show.
    $page->script("() => { history.pushState({}, '', '/me/acme/punch'); window.dispatchEvent(new PopStateEvent('popstate')); }");

    $page->waitForText('Marcar sin internet')
        ->assertSee('Entrada')
        ->assertSee('Salir a comer')
        ->assertSee('Leer el código de la pantalla')
        ->assertSee('Reintentar con internet')
        ->assertDontSee('Ana')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);

    markOffline($page, 'Salir a comer', 'Salida a comer sin internet');
    $page->assertSee('1 marca sin enviar')->assertNoAccessibilityIssues(1);

    withoutNetwork($page, false);
    $page->press('Listo');
    $page->assertScript(eventually('() => document.body.innerText.includes("Recibida.")'), true);

    expect(AttendanceOfflineMark::query()->sole()->outcome)->toBe('accepted');
})->with(['light' => [false]]);

it('opens the shell with the count of the marks that wait and the way to Marcar', function (): void {
    $page = phoneWithTickets();
    withoutNetwork($page);
    markOffline($page, 'Salir a comer', 'Salida a comer sin internet');

    $page->navigate('/me/acme/offline')->waitForText('No hay internet')
        ->assertSee('1 marca sin enviar')
        ->assertPresent('a[href="/me/acme/punch"]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);
});
