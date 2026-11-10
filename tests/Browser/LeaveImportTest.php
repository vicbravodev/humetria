<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;

// Light and dark, on a desktop and on a phone.
$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

/**
 * Ana was hired on 10 May 2022 and the company tracks vacations since 1 January 2026; returns the address of the import and a
 * file with one row that comes in and one that does not.
 *
 * @return array{string, string}
 */
function leaveImportBrowserSetup(): array
{
    test()->travelTo('2026-03-20 18:00:00');
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2026-01-01']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Solís', 'employee_number' => 'A-001', 'hired_on' => '2022-05-10']);
    $path = tempnam(sys_get_temp_dir(), 'saldos').'.csv';
    file_put_contents($path, "numero_de_empleado,aniversario,dias_pendientes\nA-001,2025,10\nZ-999,2025,5\n");

    return ["{$url}/vacations/import", $path];
}

it('shows the steps and how to start', function (bool $dark, bool $mobile): void {
    [$url] = leaveImportBrowserSetup();

    visitPage('leave/import', $url, $dark, $mobile)
        ->assertSee('Cargar saldos iniciales')
        ->assertSee('Descargar plantilla')
        ->assertSee('Revisar archivo')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('checks the file, says why a row stays out, and loads only on confirming', function (): void {
    [$url, $path] = leaveImportBrowserSetup();

    visitPage('leave/import', $url)
        ->attach('#leave-import-file', $path)
        ->press('Revisar archivo')
        ->assertSee('Ana Solís')
        ->assertSee('No hay una persona con ese número de empleado.')
        ->assertSee('Se cargarán 1 periodo; 1 fila se queda fuera.')
        ->assertNoJavaScriptErrors();

    expect(LeaveEntitlement::query()->count())->toBe(0);
});
