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
    $enroll = (string) parse_url(enrollmentUrl(test()->ana->id), PHP_URL_PATH);
    $page = visitPage('attendance/enroll', $enroll, $dark, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    $page->click('Leí el acuerdo y lo acepto.')->press('Aceptar y vincular este teléfono')->waitForText('Su teléfono quedó vinculado');

    return $page->navigate('/a/acme/punch?q='.QrToken::issue(test()->tenant->id, test()->plant->id, CarbonImmutable::now()));
}

it('opens the screen of Marcar for a device not known yet: every step, why the position is asked, and how to link', function (bool $dark, bool $mobile): void {
    visitPage('attendance/punch', '/a/acme', $dark, $mobile)
        ->assertSee('Marcar su jornada')
        ->assertSee('Marcar entrada')
        ->assertSee('Salir a comer')
        ->assertSee('Al marcar le pediremos su ubicación una sola vez')
        ->assertSee('¿Aún no vincula este teléfono?');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('marks the entry inside the zone with the QR, and shows the day and the next step', function (bool $dark): void {
    $page = phoneAtThePunchScreen($dark);
    standingAt($page, 19.4327, -99.1332);

    $page->press('Marcar entrada')
        ->waitForText('Listo: se registró su entrada')
        ->assertSee('Su día')
        ->assertSee('Salir a comer')
        ->assertDontSee('Regresar de comer')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);

    $mark = AttendanceMark::query()->sole();
    expect($mark->method)->toBe(MarkMethod::QrAndGeofence)->and($mark->employee_id)->toBe($this->ana->id)->and($mark->zone_id)->not->toBeNull();
})->with(['light' => [false], 'dark' => [true]]);

it('blocks the mark outside the zone and says what to do', function (): void {
    $page = phoneAtThePunchScreen();
    standingAt($page, 19.4336, -99.1332);

    $page->press('Marcar entrada')
        ->waitForText('Está fuera de la zona de su centro de trabajo')
        ->assertSee('pida a Recursos Humanos una corrección')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);

    expect(AttendanceMark::query()->count())->toBe(0);
});

it('asks for the location when the person does not allow it', function (): void {
    $page = phoneAtThePunchScreen();

    $page->press('Marcar entrada')
        ->waitForText('Para marcar se necesita su ubicación')
        ->assertNoJavaScriptErrors();

    expect(AttendanceMark::query()->count())->toBe(0);
});
