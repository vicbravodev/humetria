<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Notification;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Playwright;

beforeEach(function (): void {
    Playwright::setHost('localhost');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    // A 50 m zone around 19.4326, -99.1332.
    AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López']);
});

afterEach(function (): void {
    Playwright::setHost(null);
});

/** Ana's phone, linked from the link RH sent her, then opening Marcar from the QR of her center's screen. */
function phoneAtThePunchScreen(bool $dark = false): AwaitableWebpage
{
    $enroll = accessLinkPath(test()->ana->id);
    $page = visitPage('worker-access/link', $enroll, $dark, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    linkThePhone($page, test()->ana->id);

    // The test goes on once the screen of Marcar is really there, not while the browser is still settling the navigation.
    return $page->navigate('/a/acme/punch?q='.QrToken::issue(test()->tenant->id, test()->plant->id, CarbonImmutable::now()))
        ->waitForText('Marcar su jornada');
}

it('opens the screen of Marcar for a device not known yet: every step, why the position is asked, and how to link', function (bool $dark, bool $mobile): void {
    visitPage('attendance/punch', '/a/acme', $dark, $mobile)
        ->assertSee('Marcar su jornada')
        ->assertSee('Marcar entrada')
        ->assertSee('Salir a comer')
        ->assertSee('Al marcar, el teléfono le pedirá su ubicación: si usted trabaja en un centro, la usamos para comprobar que está ahí; si no, se descarta. No se guarda en este teléfono ni se sigue entre marcas.')
        ->assertSee('Si aún no tiene una llave de acceso')
        ->assertSee('Si ya tiene llave aquí, solo marque.')
        // Whether the center asks for the screen's code is the server's to say when marking: until then it says how.
        ->assertSee('Si su centro tiene una pantalla QR');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('marks the entry inside the zone with the QR, and shows the day and the next step', function (bool $dark): void {
    $page = phoneAtThePunchScreen($dark);
    standingAt($page, 19.4327, -99.1332);

    $page->press('button:has-text("Marcar entrada")')
        ->waitForText('Entrada registrada')
        ->assertSee('Evidencia fuerte: QR y ubicación')
        ->assertSee('Su día')
        ->assertSee('Su registro no se puede borrar')
        ->assertSee('Ver mi semana')
        // The big button is gone: the day is told, and the next step comes back with «Listo».
        ->assertDontSee('Marcar entrada')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1)
        ->press('Listo')
        ->assertSee('Está trabajando')
        ->assertSee('Salir a comer')
        ->assertSee('Marcar salida')
        ->assertDontSee('Regresar de comer')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);

    $mark = AttendanceMark::query()->sole();
    expect($mark->method)->toBe(MarkMethod::QrAndGeofence)->and($mark->employee_id)->toBe($this->ana->id)->and($mark->zone_id)->not->toBeNull();
})->with(['light' => [false], 'dark' => [true]]);

it('shows each test while the mark is checked, so the person sees it pass', function (): void {
    $page = phoneAtThePunchScreen();
    standingAt($page, 19.4327, -99.1332);
    // The server answers slowly, as on a bad connection.
    $page->script('() => { const real = window.fetch; window.fetch = (url, init) => String(url).endsWith("/punch") ? new Promise((resolve) => setTimeout(() => resolve(real(url, init)), 2500)) : real(url, init); }');

    $page->press('button:has-text("Marcar entrada")')
        ->waitForText('Comprobando el código y la zona')
        ->assertSee('Verificando')
        ->assertSee('Leyendo su ubicación')
        ->assertSee('Confirme con su huella, su rostro o su PIN')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);

    // Position and fingerprint passed (the browser's reader is instant); the server's check is the one in course.
    $page->assertScript(eventually('() => document.querySelector("[aria-current=step]").textContent === "Comprobando el código y la zona"'), true)
        ->waitForText('Entrada registrada');
});

it('blocks the mark outside the zone and says what happened and what to do', function (): void {
    $page = phoneAtThePunchScreen();
    standingAt($page, 19.4336, -99.1332);

    $page->press('button:has-text("Marcar entrada")')
        ->waitForText('Está fuera de la zona')
        ->assertSee('Acérquese a su centro de trabajo')
        ->assertSee('pida a Recursos Humanos una corrección')
        ->assertSee('Intentar de nuevo')
        ->assertSee('Pedir una corrección')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);

    expect(AttendanceMark::query()->count())->toBe(0);

    // «Intentar de nuevo» marks again, now from inside the zone.
    standingAt($page, 19.4327, -99.1332);
    $page->press('Intentar de nuevo')->waitForText('Entrada registrada');

    expect(AttendanceMark::query()->sole()->zone_id)->not->toBeNull();
});

it('asks for the location when the person does not allow it', function (): void {
    $page = phoneAtThePunchScreen();

    $page->press('button:has-text("Marcar entrada")')
        ->waitForText('No tenemos su ubicación')
        ->assertSee('Permita la ubicación para este sitio')
        ->assertDontSee('Puede marcar sin ubicación')
        ->press('Cómo activarla')
        ->assertSee('Abra los ajustes de su teléfono')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);

    expect(AttendanceMark::query()->count())->toBe(0);
});

/**
 * Ana marks her entry on this phone; then the phone is handed to Bob, who links his own key on it and opens Marcar from
 * the QR. The page still remembers Ana (under 10 minutes).
 */
function bobOnAnasPhone(): AwaitableWebpage
{
    $bob = EmployeeFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->plant->id, 'name' => 'Bob Ruiz']);
    $clearKeys = null;
    $enroll = accessLinkPath(test()->ana->id);
    $page = visitPage('worker-access/link', $enroll, mobile: true, reach: function (AwaitableWebpage $page) use (&$clearKeys): void {
        $clearKeys = withFingerprintReader($page);
    });
    linkThePhone($page, test()->ana->id);
    standingAt($page, 19.4327, -99.1332);
    $page->navigate('/a/acme/punch?q='.QrToken::issue(test()->tenant->id, test()->plant->id, CarbonImmutable::now()))
        ->waitForText('Marcar entrada')
        ->press('button:has-text("Marcar entrada")')
        ->waitForText('Entrada registrada')
        ->press('Listo')
        ->waitForText('Está trabajando')
        ->assertScript('() => document.querySelector("h1").textContent.endsWith(", Ana")', true);

    expect($clearKeys)->toBeInstanceOf(Closure::class);
    $clearKeys();
    linkThePhone($page->navigate(accessLinkPath($bob->id)), $bob->id);

    return $page->navigate('/a/acme/punch?q='.QrToken::issue(test()->tenant->id, test()->plant->id, CarbonImmutable::now()))
        ->assertScript('() => document.querySelector("h1").textContent.endsWith(", Ana")', true)
        ->assertDontSee('Su día');
}

it('shows Bob his own name and steps when he marks on the phone that remembers Ana, and never her day', function (): void {
    $page = bobOnAnasPhone();

    // Bob taps the step offered to Ana: rejected, the screen now speaks to him and offers every step.
    $page->press('button:has-text("Salir a comer")')
        ->waitForText('Primero marque su entrada.')
        ->assertScript('() => document.querySelector("h1").textContent.endsWith(", Bob")', true)
        ->assertScript('() => /\\bAna\\b/.test(document.body.innerText)', false)
        ->assertSee('Marcar entrada')
        ->press('button:has-text("Marcar entrada")')
        ->waitForText('Entrada registrada')
        ->assertScript('() => /\\bAna\\b/.test(document.body.innerText)', false)
        ->press('Listo')
        ->waitForText('Está trabajando')
        ->assertScript('() => document.querySelector("h1").textContent.endsWith(", Bob")', true)
        ->assertSee('Salir a comer')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);

    // Asked for his position: the on-site mark needs it, and it is inside the zone.
    expect(AttendanceMark::query()->where('employee_id', '!=', $this->ana->id)->sole()->zone_id)->not->toBeNull();
});

it('says which day the correction asked from Marcar is about', function (): void {
    $page = phoneAtThePunchScreen();
    standingAt($page, 19.4336, -99.1332);

    $page->press('button:has-text("Marcar entrada")')
        ->waitForText('Está fuera de la zona')
        ->press('Pedir una corrección')
        ->waitForText('Pedir a Recursos Humanos que agregue su marca')
        // «Día: jueves 12 de noviembre»: the day of the mark refused, in the company's time.
        ->assertScript('() => /Día: [a-záéíóúñ]+ \\d{1,2} de [a-záéíóúñ]+/.test(document.body.innerText)', true)
        ->assertNoJavaScriptErrors();
});

it('forgets Ana at once when Bob says it is not him («¿No es usted?»)', function (): void {
    $page = bobOnAnasPhone();

    $page->press('¿No es usted?')
        ->waitForText('Marcar su jornada')
        ->assertScript('() => /\\bAna\\b/.test(document.body.innerText)', false)
        ->assertSee('Si aún no tiene una llave de acceso')
        ->assertSee('Si ya tiene llave aquí, solo marque.')
        ->press('button:has-text("Marcar entrada")')
        ->waitForText('Entrada registrada')
        ->press('Listo')
        ->waitForText('Está trabajando')
        ->assertScript('() => document.querySelector("h1").textContent.endsWith(", Bob")', true)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});
