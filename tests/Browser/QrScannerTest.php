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

/*
 * Reading the QR of the screen of the person's center with the phone's own camera, inside Marcar. The camera is a canvas
 * that draws the code (withCameraShowing); whether a real camera sees a screen's moiré or low light is checked in phones.
 */

beforeEach(function (): void {
    Playwright::setHost('localhost');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López']);
});

afterEach(function (): void {
    Playwright::setHost(null);
});

/** Marcar of a device nobody is known in, with a camera that shows the QR of `$path` on this origin. */
function punchScreenSeeing(string $path): AwaitableWebpage
{
    $page = visit('/me/acme/punch')->assertNoJavaScriptErrors()->waitForText('Marcar su jornada');
    withCameraShowing($page, originOf($page).$path);

    return $page;
}

/** Whether every track of the camera the page opened has ended (the phone's camera indicator is off). */
function cameraIsOff(): string
{
    return eventually('() => window.__camera !== undefined && window.__camera.getTracks().every((track) => track.readyState === "ended")');
}

it('keeps the camera open to the person\'s pages when they move from the start to Marcar, with no full load', function (): void {
    $enroll = accessLinkPath($this->ana->id);
    $page = visitPage('worker-access/link', $enroll, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    linkThePhone($page, $this->ana->id);

    $page->click('nav[aria-label="Secciones del portal"] a[href$="/me/acme/punch"]')
        ->waitForText(', Ana')
        ->assertScript('() => (document.permissionsPolicy ?? document.featurePolicy).allowsFeature("camera")', true)
        ->assertScript('() => (document.permissionsPolicy ?? document.featurePolicy).allowsFeature("geolocation")', true);
});

it('says the camera is off when the phone does not allow it, that Marcar may still work, and lets the person try again or go back', function (): void {
    $page = visit('/me/acme/punch')->assertNoJavaScriptErrors()->waitForText('Marcar su jornada');
    $page->script('() => { window.__asked = 0; navigator.mediaDevices.getUserMedia = async () => { window.__asked++; throw new DOMException("no", "NotAllowedError"); }; return true; }');

    $page->press('button:has-text("Entrada")')
        ->waitForText('La cámara está apagada')
        ->assertSee('Sin cámara también puede marcar')
        ->assertSee('En iPhone: Ajustes › Humetría › Cámara')
        ->assertSee('En Android: Ajustes › Apps › Humetría › Permisos')
        ->assertNoAccessibilityIssues(1)
        ->press('[role="dialog"] button:has-text("Intentar de nuevo")')
        ->assertScript(eventually('() => window.__asked === 2'), true)
        ->assertSee('La cámara está apagada')
        ->press('[role="dialog"] button:has-text("Volver a Marcar")')
        ->assertMissing('[role="dialog"]')
        ->assertNoJavaScriptErrors();
});

it('reads the code of the screen, turns the camera off and says so', function (): void {
    $page = punchScreenSeeing('/me/acme/punch?q=abc123def');

    // Tapping the mark the policy asks the code for opens the reader; once read, the camera is off and the mark goes on to the key.
    $page->press('button:has-text("Entrada")')
        ->assertScript(cameraIsOff(), true)
        ->assertMissing('[role="dialog"]')
        ->assertNoJavaScriptErrors();
});

it('reads the branded code of Humetría too: rounded squares, level H and the arch of the logo in the middle', function (): void {
    config(['attendance.qr.branded' => true]);
    // The length of a real code of a screen: the densest the reader will see.
    $page = punchScreenSeeing('/me/acme/punch?q=59852987.926f3b58d6b5586c8f0247230f593794be03e98f3361b7897d50b11e56045277');

    $page->press('button:has-text("Entrada")')
        ->assertScript(cameraIsOff(), true);
});

it('marks with the code the camera read: the mark counts with the QR and the zone', function (): void {
    AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $enroll = accessLinkPath($this->ana->id);
    $page = visitPage('worker-access/link', $enroll, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    linkThePhone($page, $this->ana->id);
    standingAt($page, 19.4327, -99.1332);

    // Marcar opened without the code in its address: the only code there is, is the one the camera reads.
    $page->navigate('/me/acme/punch')->waitForText(', Ana');
    withCameraShowing($page, originOf($page).'/me/acme/punch?q='.QrToken::issue($this->tenant->id, $this->plant->id, CarbonImmutable::now()));

    // One tap: the reader opens, reads the code and the key follows, with no second button to choose.
    $page->press('button:has-text("Entrada")')
        ->waitForText('Entrada registrada')
        ->assertSee('Con QR y ubicación');

    expect(AttendanceMark::query()->sole()->method)->toBe(MarkMethod::QrAndGeofence);
});

it('says a code of another company is not theirs and goes on reading', function (): void {
    $page = punchScreenSeeing('/me/otra/punch?q=abc123def');

    $page->press('button:has-text("Entrada")')
        ->waitForText('Ese código es de otra empresa.')
        ->assertPresent('[role="dialog"]')
        ->assertDontSee('Código leído')
        ->assertScript('() => window.__camera.getTracks().every((track) => track.readyState === "live")', true)
        ->assertNoAccessibilityIssues(1);
});

it('does not take the old address of Marcar for a code: it says it is not the center\'s and goes on reading', function (): void {
    $page = punchScreenSeeing('/a/acme/punch?q=abc123def');

    $page->press('button:has-text("Entrada")')
        ->waitForText('Ese código no es el de la pantalla de su centro. Apunte al de la pantalla.')
        // Long enough for the camera to have seen the code several times.
        ->assertScript('() => new Promise((resolve) => setTimeout(resolve, 2000)).then(() => document.body.innerText.includes("Código leído"))', false)
        ->assertPresent('[role="dialog"]')
        ->assertScript('() => window.__camera.getTracks().every((track) => track.readyState === "live")', true);
});

it('says a code of another address is not the center\'s, which is what a screen opened from another address shows', function (): void {
    // The screen of the center was opened from `localhost` while this phone is at another address: the code carries that one.
    $page = visit('/me/acme/punch')->waitForText('Marcar su jornada');
    withCameraShowing($page, 'https://another-address.test/me/acme/punch?q=59852987.926f3b58d6b5586c');

    $page->press('button:has-text("Entrada")')
        ->waitForText('Ese código no es el de la pantalla de su centro. Apunte al de la pantalla.')
        ->assertNoAccessibilityIssues(1);
});

it('says it cannot read anything when no code shows for a while, and goes on looking', function (): void {
    $page = visit('/me/acme/punch')->waitForText('Marcar su jornada');
    withCameraShowingNoCode($page);

    $page->press('button:has-text("Entrada")')
        ->waitForText('Apunte al código de la pantalla de su centro.')
        ->assertDontSee('No logro leer el código')
        ->waitForText('No logro leer el código. Acérquese o aléjese un poco, sin reflejos, y mantenga el teléfono quieto.', 15)
        ->assertPresent('[role="dialog"]')
        ->assertScript('() => window.__camera.getTracks().every((track) => track.readyState === "live")', true)
        ->assertNoAccessibilityIssues(1);
});

it('turns the camera off when it is closed and when the page is left', function (): void {
    $page = punchScreenSeeing('/a/acme/punch?q=abc123def');

    $page->press('button:has-text("Entrada")')
        ->waitForText('Apunte al código de la pantalla de su centro.')
        ->press('button:has-text("Cerrar cámara")')
        ->assertScript(cameraIsOff(), true)
        ->assertMissing('[role="dialog"]');

    $page->press('button:has-text("Entrada")')
        ->waitForText('Apunte al código de la pantalla de su centro.')
        ->script('() => window.dispatchEvent(new Event("pagehide"))');

    $page->assertScript(cameraIsOff(), true);
});
