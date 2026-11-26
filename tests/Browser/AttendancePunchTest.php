<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Notifications\Models\PushSubscription;
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
    return openPath($page, '/me/acme/punch?q='.QrToken::issue(test()->tenant->id, test()->plant->id, CarbonImmutable::now()))
        ->waitForText(', Ana');
}

it('opens the screen of Marcar for a device not known yet: every step compact, «Mi semana» on top, why the position is asked and how to link, to open', function (bool $dark, bool $mobile): void {
    visitPage('attendance/punch', '/me/acme/punch', $dark, $mobile)
        ->assertSee('Marcar su jornada')
        ->assertSee('Entrada')
        ->assertSee('Salida')
        ->assertSee('Salir a comer')
        ->assertSee('Regresar de comer')
        ->assertSee('Vea sus marcas y sus horas')
        // The way back to the portal (hallazgo 17).
        ->assertPresent('header a[href$="/me/acme"]')
        // Whether the center asks for the screen's code is the server's to say when marking: until then it says how.
        ->assertSee('Si su centro tiene una pantalla QR')
        // The two long texts open to read, whole (hallazgo 16).
        ->assertDontSee('No se guarda en este teléfono ni se sigue entre marcas.')
        ->click('summary:has-text("Sobre su ubicación")')
        ->assertSee('Al marcar, el teléfono le pedirá su ubicación: si usted trabaja en un centro, la usamos para comprobar que está ahí; si no, se descarta. No se guarda en este teléfono ni se sigue entre marcas.')
        ->click('summary:has-text("¿Aún no tiene su llave?")')
        ->assertSee('Si ya creó su llave en este dispositivo, solo marque.')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('marks the entry inside the zone with the QR, and shows the day and the next step', function (bool $dark): void {
    $page = phoneAtThePunchScreen($dark);
    standingAt($page, 19.4327, -99.1332);

    $page->press('button:has-text("Entrada")')
        ->waitForText('Entrada registrada')
        // How it was taken in words, in its day, without a measure (hallazgo 20).
        ->assertSee('Con QR y ubicación')
        ->assertDontSee('Evidencia')
        ->assertSee('Su día')
        ->assertSee('Su registro no se puede borrar')
        ->assertSee('Ver mi semana')
        // The big button is gone: the day is told, and the next step comes back with «Listo».
        ->assertDontSee('Marcar entrada')
        ->assertPresent('button:has-text("Listo")')
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

    $page->press('button:has-text("Entrada")')
        ->waitForText('Comprobando el código y la zona')
        ->assertSee('Verificando')
        ->assertSee('Leyendo su ubicación')
        ->assertSee('Confirme con su huella, su rostro o su PIN')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);

    // Position and fingerprint passed (the browser's reader is instant); the server's check is the one in course.
    $page->assertScript(eventually('() => document.querySelector("[aria-current=step]").textContent === "Comprobando el código y la zona: en curso"'), true)
        ->waitForText('Entrada registrada');
});

it('blocks the mark outside the zone and says what happened and what to do', function (): void {
    $page = phoneAtThePunchScreen();
    standingAt($page, 19.4336, -99.1332);

    $page->press('button:has-text("Entrada")')
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

    $page->press('button:has-text("Entrada")')
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
    openPath($page, '/me/acme/punch?q='.QrToken::issue(test()->tenant->id, test()->plant->id, CarbonImmutable::now()))
        ->waitForText(', Ana')
        ->press('button:has-text("Entrada")')
        ->waitForText('Entrada registrada')
        ->press('Listo')
        ->waitForText('Está trabajando')
        ->assertScript('() => document.querySelector("h1").textContent.endsWith(", Ana")', true);

    expect($clearKeys)->toBeInstanceOf(Closure::class);
    $clearKeys();
    linkThePhone(openPath($page, accessLinkPath($bob->id)), $bob->id);

    return openPath($page, '/me/acme/punch?q='.QrToken::issue(test()->tenant->id, test()->plant->id, CarbonImmutable::now()))
        ->assertScript('() => document.querySelector("h1").textContent.endsWith(", Ana")', true)
        ->assertDontSee('Su día');
}

it('shows Bob his own name and steps when he marks on the phone that remembers Ana, and never her day', function (): void {
    $page = bobOnAnasPhone();

    // Bob taps the step offered to Ana: rejected, the screen now speaks to him and offers his step, with a word on why.
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

    $page->press('button:has-text("Entrada")')
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
        ->assertSee('¿Aún no tiene su llave?')
        ->press('button:has-text("Entrada")')
        ->waitForText('Entrada registrada')
        ->press('Listo')
        ->waitForText('Está trabajando')
        ->assertScript('() => document.querySelector("h1").textContent.endsWith(", Bob")', true)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('remembers in the browser that a mark was accepted, only then: that is when the start may offer the notices', function (): void {
    $page = phoneAtThePunchScreen();
    standingAt($page, 19.4327, -99.1332);
    $page->assertScript("() => localStorage.getItem('humetria-push-marked')", null);

    $page->press('button:has-text("Entrada")')->waitForText('Entrada registrada');

    $page->assertScript("() => localStorage.getItem('humetria-push-marked')", '1');
});

it('does not remember a mark that was refused', function (): void {
    $page = phoneAtThePunchScreen();
    standingAt($page, 19.4336, -99.1332);

    $page->press('button:has-text("Entrada")')->waitForText('Está fuera de la zona');

    $page->assertScript("() => localStorage.getItem('humetria-push-marked')", null);
});

it('gives the phone\'s notices up with «¿No es usted?»: the server forgets it and the browser drops it', function (): void {
    PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/ana-phone']);
    $page = phoneAtThePunchScreen();
    standingAt($page, 19.4327, -99.1332);
    $page->press('button:has-text("Entrada")')->waitForText('Entrada registrada')->press('Listo')->waitForText('Está trabajando');
    // The worker's registration is a double (a real push manager does not exist in the test): this phone is subscribed as Ana's.
    $page->script(<<<'JS'
        () => {
            const endpoint = 'https://fcm.googleapis.com/fcm/send/ana-phone';
            const subscription = { endpoint, options: { applicationServerKey: null }, unsubscribe: () => { window.unsubscribed = true; return Promise.resolve(true); } };
            navigator.serviceWorker.getRegistration = () => Promise.resolve({ pushManager: { getSubscription: () => Promise.resolve(subscription) } });
        }
        JS);

    $page->press('¿No es usted?')->waitForText('Marcar su jornada');

    $page->assertScript('() => window.unsubscribed === true', true);
    expect(PushSubscription::query()->count())->toBe(0);
});

it('keeps the steps compact at the foot of a phone, so the content is seen first (hallazgo 16)', function (): void {
    visitPage('attendance/punch', '/me/acme/punch', mobile: true)
        // The bar of actions took 353 px of 844; the board's takes about 190.
        ->assertScript('() => document.querySelector(".sticky.bottom-0").getBoundingClientRect().height <= 200', true)
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('tells a person who scanned the screen how long the scan serves (hallazgo 27)', function (): void {
    phoneAtThePunchScreen()
        ->assertScript('() => /Escaneó a las \\d{2}:\\d{2}, tiene 3 minutos para marcar\\./.test(document.body.innerText)', true);
});

it('asks a remote person for no code nor zone, and not for the position (hallazgo 38)', function (bool $dark): void {
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_mode' => WorkMode::Remote]);
    $enroll = accessLinkPath($this->ana->id);
    $page = visitPage('worker-access/link', $enroll, $dark, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    linkThePhone($page, $this->ana->id);

    // From her portal: the page knows how she marks before she signs. She never lets the page read her position.
    openPath($page, '/me/acme/punch')
        ->waitForText(', Ana')
        ->assertSee('Marque desde donde esté: no se le pedirá un QR ni estar en una zona.')
        ->assertDontSee('Sobre su ubicación')
        ->assertDontSee('Si su centro tiene una pantalla QR')
        ->assertNoAccessibilityIssues(1)
        ->press('button:has-text("Entrada")')
        ->waitForText('Entrada registrada')
        ->assertSee('Con su llave')
        ->assertNoJavaScriptErrors();

    expect(AttendanceMark::query()->sole())->method->toBe(MarkMethod::Remote)->latitude->toBeNull();
})->with(['light' => [false], 'dark' => [true]]);

/** Counts on the page every signature it asks for: each one starts with its options. */
function countSignatures(): string
{
    return '() => { window.signatures = 0; const real = window.fetch; window.fetch = (url, init) => { if (String(url).endsWith("/options")) { window.signatures++; } return real(url, init); }; }';
}

it('accepts the agreement and marks the step that proceeds with one signature, whatever step was tapped (hallazgo 27)', function (): void {
    $enroll = accessLinkPath($this->ana->id);
    $page = visitPage('worker-access/link', $enroll, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    // Linked, without the agreement accepted.
    $page->press('button:has-text("Continuar")')->waitForText('cree su llave de acceso')->press('button:has-text("Crear llave")')->waitForText('Pendientes');
    standingAt($page, 19.4327, -99.1332);
    openPath($page, '/me/acme/punch?q='.QrToken::issue($this->tenant->id, $this->plant->id, CarbonImmutable::now()))
        ->waitForText(', Ana')
        ->press('¿No es usted?')
        ->waitForText('Regresar de comer')
        ->script(countSignatures());

    $page->press('button:has-text("Salir a comer")')
        ->waitForText('Antes de marcar, acepte el acuerdo')
        ->assertSee('Acuerdo de registro · versión 1')
        ->assertSee('Valor de prueba')
        ->assertSee('En una marca hecha sin conexión, la hora que usted indica se acota')
        ->assertDontSee('La hora la pone el servidor, no su teléfono')
        ->assertSee('Ahora no')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1)
        ->press('Acepto y marcar entrada')
        ->waitForText('Entrada registrada')
        ->assertScript('() => window.signatures', 2)
        ->assertNoJavaScriptErrors();

    expect(AgreementAcceptance::query()->where('employee_id', $this->ana->id)->count())->toBe(1)
        ->and(AttendanceMark::query()->sole()->type)->toBe(MarkType::Start);
});

it('spends no signature on a step that does not proceed: «Intentar de nuevo» says which goes first', function (): void {
    $page = phoneAtThePunchScreen();
    standingAt($page, 19.4336, -99.1332);

    // The four steps are offered to a phone that does not greet anyone: the person taps one that does not proceed.
    $page->press('¿No es usted?')
        ->waitForText('Regresar de comer')
        ->press('button:has-text("Salir a comer")')
        ->waitForText('Está fuera de la zona')
        ->script(countSignatures());

    $page->press('Intentar de nuevo')
        ->waitForText('Primero marque su entrada.')
        ->assertSee('Marcar entrada')
        ->assertScript('() => window.signatures', 0);
});

it('tells an expired code apart and opens Marcar again without it, to scan the one on screen (hallazgo 26)', function (): void {
    $page = phoneAtThePunchScreen();
    standingAt($page, 19.4327, -99.1332);
    $this->travel(5)->minutes();

    $page->press('button:has-text("Entrada")')
        ->waitForText('Ese código ya venció')
        ->assertSee('Escanee de nuevo')
        ->assertNoAccessibilityIssues(1)
        ->press('Escanee de nuevo')
        // The page that remembers her for a few minutes, now without the old code: it says how to scan the new one.
        ->waitForText('Al marcar, primero lea el código de la pantalla de su centro')
        ->assertScript('() => window.location.search', '')
        ->assertScript('() => document.querySelector("h1").textContent.endsWith(", Ana")', true);
});
