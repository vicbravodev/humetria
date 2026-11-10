<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\IssueKioskDevice;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\WorkCenterFactory;

/** The link RH hands to the QR screen of «Planta Norte» of Acme. */
function kioskLink(): string
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    workIn($tenant);
    $token = resolve(IssueKioskDevice::class)->handle(WorkCenterFactory::new()->createOne(['tenant_id' => $tenant->id, 'name' => 'Planta Norte'])->id);

    return route('attendance.device.kiosk.activate', [$tenant->slug, $token], absolute: false);
}

it('shows the center\'s code, large, with the seconds it has left, the company\'s clock and that it reaches the server', function (bool $dark, bool $mobile): void {
    visitPage('attendance/kiosk', kioskLink(), $dark, $mobile)
        ->assertSee('Planta Norte')
        ->assertSee('Acme')
        ->waitForText('Conectada')
        ->assertSee('Escanee para marcar')
        ->assertSee('Cambia en')
        ->assertSee('Un código nuevo cada 30 segundos')
        ->assertPresent('img[alt="Código para registrar su entrada o su salida"]')
        ->assertSee('Apunte la cámara de su teléfono al código')
        ->assertScript('() => /\d{2}:\d{2}/.test(document.querySelector("header").textContent)', true)
        ->assertScript('() => window.location.pathname.endsWith("/kiosk")', true);
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
]);

it('counts the seconds until the code turns', function (): void {
    // Frozen at 12:00:10 of a 30 s window: every answer says 20 s.
    $this->travelTo(CarbonImmutable::parse('2026-11-07 12:00:10', 'UTC'));
    $seconds = '() => Number(document.body.innerText.match(/Cambia en (\d+) s/)?.[1] ?? -1)';

    $page = visitPage('attendance/kiosk', kioskLink())->waitForText('Cambia en');
    $first = $page->script($seconds);
    $page->wait(2.2);

    expect($first)->toBeGreaterThan(15)->and($page->script($seconds))->toBeLessThan($first - 1);
});

it('says when it lost the connection, shows no code at all, and keeps retrying', function (): void {
    // One second before the code turns: the screen asks again in a second and a half.
    $this->travelTo(CarbonImmutable::parse('2026-11-07 12:00:29', 'UTC'));

    $page = visitPage('attendance/kiosk', kioskLink())->waitForText('Conectada');
    $page->script('() => { window.fetch = () => Promise.reject(new TypeError("offline")); }');

    // A code on the wall that no longer turns could be photographed and used from elsewhere: nothing of it stays on the screen.
    $page->waitForText('Esta pantalla perdió la conexión')
        ->assertSee('Sin conexión')
        ->assertSee('Último código válido')
        ->assertSee('No se muestran códigos mientras no haya internet')
        ->assertSee('avise a RH')
        ->assertMissing('img[alt="Código para registrar su entrada o su salida"]')
        ->assertScript('() => document.documentElement.outerHTML.includes("data:image/svg+xml")', false)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertNoAccessibilityIssues(1);
});

it('asks again at once when «Reintentar ahora» is pressed, and shows a code only when the server answers', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-07 12:00:29', 'UTC'));

    $page = visitPage('attendance/kiosk', kioskLink())->waitForText('Conectada');
    $page->script('() => { window.realFetch = window.fetch; window.fetch = () => Promise.reject(new TypeError("offline")); }');
    $page->waitForText('Esta pantalla perdió la conexión');

    // The network is back: it does not wait for its own retry.
    $page->script('() => { window.fetch = window.realFetch; }');
    $page->press('Reintentar ahora')
        ->waitForText('Conectada')
        ->assertPresent('img[alt="Código para registrar su entrada o su salida"]')
        ->assertDontSee('Esta pantalla perdió la conexión')
        ->assertNoJavaScriptErrors();
});

it('keeps turning after an answer that is not JSON, as from a captive portal, and recovers', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-07 12:00:29', 'UTC'));

    $page = visitPage('attendance/kiosk', kioskLink())->waitForText('Conectada');
    // The next answer is a 200 page of HTML; the one after, the server's again.
    $page->script('() => { const real = window.fetch; window.fetch = (...args) => { window.fetch = real; return Promise.resolve(new Response("<html>Wi-Fi</html>", { status: 200 })); }; }');

    $page->waitForText('Esta pantalla perdió la conexión')
        ->assertMissing('img[alt="Código para registrar su entrada o su salida"]')
        ->waitForText('Conectada')
        ->assertPresent('img[alt="Código para registrar su entrada o su salida"]')
        ->assertNoJavaScriptErrors();
});

it('opens from a link tapped on another site, as in an email or a chat', function (): void {
    $link = kioskLink();
    $page = visit('/');
    // 127.0.0.1 and localhost are different sites: the browser treats the tap as a cross-site navigation.
    $other = str_replace('127.0.0.1', 'localhost', rtrim($page->url(), '/'));
    $page->script("() => { window.location.href = '{$other}{$link}'; }");

    $page->waitForText('Conectada')
        ->assertSee('Planta Norte')
        ->assertNoJavaScriptErrors();
});
