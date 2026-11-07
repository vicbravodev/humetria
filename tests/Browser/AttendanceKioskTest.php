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

it('shows the center\'s code, large, and that it reaches the server', function (bool $dark, bool $mobile): void {
    visitPage('attendance/kiosk', kioskLink(), $dark, $mobile)
        ->assertSee('Planta Norte')
        ->assertSee('Acme')
        ->waitForText('Conectado')
        ->assertPresent('img[alt="Código para registrar su entrada o su salida"]')
        ->assertSee('Escanee el código con la cámara de su teléfono')
        ->assertScript('() => window.location.pathname.endsWith("/kiosk")', true);
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
]);

it('says when it lost the connection and keeps retrying', function (): void {
    // One second before the code turns: the screen asks again in a second and a half.
    $this->travelTo(CarbonImmutable::parse('2026-11-07 12:00:29', 'UTC'));

    $page = visitPage('attendance/kiosk', kioskLink())->waitForText('Conectado');
    $page->script('() => { window.fetch = () => Promise.reject(new TypeError("offline")); }');

    $page->waitForText('Sin conexión, reintentando')
        ->assertPresent('img[alt="Código para registrar su entrada o su salida"]')
        ->assertNoJavaScriptErrors();
});
