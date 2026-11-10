<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/**
 * Signs in the owner of Acme, whose director leads two managers: Gerardo, with Ana and Beto, and Sofía, with
 * nobody. Without `$lines`, the same people with no manager at all.
 *
 * @return array<string, Employee>
 */
function acmeOrgChart(bool $lines = true): array
{
    signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $place = [
        'tenant_id' => $tenant->id,
        'work_center_id' => WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte'])->id,
        'area_id' => Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Producción'])->id,
    ];
    $person = fn (string $name, string $title, ?Employee $manager): Employee => Employee::factory()->create([...$place, 'name' => $name, 'job_title' => $title, 'manager_id' => $lines ? $manager?->id : null]);

    $dir = $person('Diana Torres', 'Directora general', null);
    $gerardo = $person('Gerardo Paz', 'Gerente de planta', $dir);

    return [
        'dir' => $dir,
        'gerardo' => $gerardo,
        'sofia' => $person('Sofía Luna', 'Gerente de ventas', $dir),
        'ana' => $person('Ana López', 'Operadora', $gerardo),
        'beto' => $person('Beto Díaz', 'Operador', $gerardo),
    ];
}

it('opens the org chart', function (bool $dark, bool $mobile): void {
    acmeOrgChart();

    visitPage('organization/org-chart', '/admin/acme/org-chart', $dark, $mobile)
        ->assertSee('Organigrama');
})->with($looks);
