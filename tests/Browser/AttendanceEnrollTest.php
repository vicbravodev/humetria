<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Notification;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Playwright;

/*
 * WebAuthn needs a domain, not an address: the browser refuses a relying party on 127.0.0.1. These tests open the app on
 * localhost (the same server) and give the page a virtual authenticator through the Chrome DevTools Protocol, as a
 * phone's fingerprint reader: Pest Browser does not expose Playwright's CDP session, so it is opened with Playwright's own
 * protocol message (BrowserContext.newCDPSession) on the page Pest drives.
 */
beforeEach(function (): void {
    Playwright::setHost('localhost');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
});

afterEach(function (): void {
    Playwright::setHost(null);
});

/** The enrollment link of Ana, as a path. */
function enrollPath(): string
{
    return (string) parse_url(enrollmentUrl(test()->ana->id), PHP_URL_PATH);
}

it('opens with the agreement in three lines, the full text behind a button, and the way out', function (bool $dark, bool $mobile): void {
    visitPage('attendance/enroll', enrollPath(), $dark, $mobile)
        ->assertSee('Paso 1 de 4')
        ->assertSee('Acuerdo de uso del teléfono')
        ->assertSee('Se guardan la hora y el lugar solo en el momento de marcar.')
        ->assertSee('No se sigue su ubicación en ningún otro momento.')
        ->assertSee('Usted puede ver todas sus marcas en «Mi semana».')
        ->assertSee('Registro de jornada')
        ->assertSee('Acepto y continúo')
        ->assertSee('Ahora no')
        // The legal text is one tap away, not hidden: it is what the person accepts.
        ->assertDontSee('Acuerdo de registro electrónico')
        ->press('Leer el acuerdo completo')
        ->assertSee('Acuerdo de registro electrónico')
        ->assertSee('Qué se guarda')
        ->assertSee('solo en el momento en que usted marca')
        ->assertNoAccessibilityIssues(1);
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('links the phone in four steps: the agreement, who the person is, the key, and what follows', function (bool $dark): void {
    $page = visitPage('attendance/enroll', enrollPath(), $dark, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));

    $page->press('Acepto y continúo')
        ->waitForText('Confirme que es usted')
        ->assertSee('Paso 2 de 4')
        ->assertSee('Ana López')
        ->assertSee($this->ana->job_title)
        ->assertSee('Si no es Ana López, cierre esta página.')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1)
        ->press('button:has-text("Sí, soy yo")')
        ->waitForText('Cree su llave de acceso')
        ->assertSee('Paso 3 de 4')
        ->assertSee('Nadie más puede marcar por usted')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);

    expect(AttendanceDevice::query()->count())->toBe(0);

    $page->press('Crear llave')
        ->waitForText('Listo, su teléfono está vinculado')
        ->assertSee('Paso 4 de 4')
        ->assertSee('Tiene la llave en este teléfono, no en el servidor')
        ->assertSee('Ir a marcar')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);

    $device = AttendanceDevice::query()->sole();
    expect($device)->employee_id->toBe($this->ana->id)->label->toBeIn(['iPhone', 'iPad', 'Android', 'Mac', 'Windows', 'Dispositivo'])
        ->and(AgreementAcceptance::query()->sole()->device_id)->toBe($device->id);

    // «Ir a marcar» goes to Marcar of the company.
    $page->click('Ir a marcar')->waitForText('Marcar su jornada')->assertScript('() => window.location.pathname', "/a/{$this->tenant->slug}");
})->with(['light' => [false], 'dark' => [true]]);

it('links nothing when the person says it is not them, and says what to do', function (): void {
    $page = visitPage('attendance/enroll', enrollPath(), mobile: true);

    $page->press('Acepto y continúo')
        ->press('No soy yo')
        ->waitForText('No se vinculó nada')
        ->assertSee('avise a Recursos Humanos')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);

    expect(AttendanceDevice::query()->count())->toBe(0);
});

it('lets the person leave with «Ahora no», and tells them the link stays good while it lasts', function (): void {
    $page = visitPage('attendance/enroll', enrollPath(), mobile: true);

    $page->press('Ahora no')
        ->waitForText('Puede vincularlo después')
        ->assertSee('48 horas')
        ->assertNoAccessibilityIssues(1);

    expect(AttendanceDevice::query()->count())->toBe(0);
});

it('says the key was not created when the person is not verified, links nothing, and lets them try again', function (): void {
    $page = visitPage('attendance/enroll', enrollPath(), reach: fn (AwaitableWebpage $page) => withFingerprintReader($page, verifies: false));

    $page->press('Acepto y continúo')
        ->press('button:has-text("Sí, soy yo")')
        ->press('Crear llave')
        ->waitForText('No se creó la llave')
        ->assertSee('confirme con su huella, su rostro o su PIN')
        ->assertSee('Intentar de nuevo')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);

    expect(AttendanceDevice::query()->count())->toBe(0);
});

it('says the link stopped working, without blame, when it was used or expired meanwhile', function (): void {
    $page = visitPage('attendance/enroll', enrollPath(), mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    EnrollmentLink::query()->update(['used_at' => now()]);

    $page->press('Acepto y continúo')
        ->press('button:has-text("Sí, soy yo")')
        ->press('Crear llave')
        ->waitForText('Este enlace ya no sirve')
        ->assertSee('Venció después de 48 horas o ya se usó')
        ->assertSee('No pasó nada con sus datos')
        ->assertSee('Copiar mensaje para RH')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);

    expect(AttendanceDevice::query()->count())->toBe(0);
});
