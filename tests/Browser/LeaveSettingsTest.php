<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveHoliday;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Tenancy\Models\Tenant;

// Light and dark, on a desktop and on a phone.
$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

/**
 * The company tracks vacations since 1 March 2026, has a type of its own and one holiday; returns the address of the settings.
 */
function leaveSettingsBrowserSetup(): string
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2026-03-01']);
    LeaveType::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Mudanza']);
    LeaveHoliday::factory()->create(['tenant_id' => $tenant->id, 'date' => '2026-11-12', 'name' => 'Aniversario de la empresa']);

    return "{$url}/vacations/settings";
}

it('shows the leave types of the law and of the company', function (bool $dark, bool $mobile): void {
    visitPage('leave/settings', leaveSettingsBrowserSetup(), $dark, $mobile)
        ->assertSee('Configuración')
        ->assertSee('Maternidad')
        ->assertSee('De la ley')
        ->assertSee('Mudanza')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('lists the holidays of the company', function (): void {
    visitPage('leave/settings', leaveSettingsBrowserSetup().'?tab=holidays')
        ->assertSee('Aniversario de la empresa')
        ->assertSee('12 de noviembre de 2026')
        ->assertNoJavaScriptErrors();
});

it('shows since which day the periods are granted and the working week', function (): void {
    visitPage('leave/settings', leaveSettingsBrowserSetup().'?tab=week')
        ->assertSee('Los periodos se otorgan desde el 1 de marzo de 2026')
        ->assertSee('De lunes a viernes')
        ->assertNoJavaScriptErrors();
});

it('adds a leave type from its dialog', function (): void {
    visitPage('leave/settings', leaveSettingsBrowserSetup())
        ->press('Nuevo tipo de permiso')
        ->fill('#type-name', 'Matrimonio')
        ->press('Guardar')
        ->assertSee('Matrimonio')
        ->assertNoJavaScriptErrors();
});
