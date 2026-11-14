<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;

/** «Mi jornada» in the portal: one place for marking, the week and the phones, light and dark, on a desktop and on a phone. */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->path = '/portal/'.resolve(IssuePortalLink::class)->handle($this->ana->id).'/attendance';
});

it('asks a person with no phone to link one, and offers marking and the week', function (bool $dark, bool $mobile): void {
    visitPage('attendance/portal', $this->path, $dark, $mobile)
        ->assertSee('Mi jornada')
        ->assertSee('Ana López')
        ->assertSee('Marcar')
        ->assertSee('Mi semana')
        ->assertSee('Todavía no vinculó ningún teléfono')
        ->assertSee('Vincular este teléfono')
        ->assertSee('Volver a mi portal')
        ->assertNoAccessibilityIssues(1);
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('lists the phones of the person and lets them link another', function (bool $dark, bool $mobile): void {
    AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone de Ana', 'enrolled_at' => '2026-10-01 12:00:00']);

    visitPage('attendance/portal', $this->path, $dark, $mobile)
        ->assertSee('iPhone de Ana')
        ->assertSee('Vinculado el')
        ->assertSee('Vincular otro teléfono')
        ->assertDontSee('Todavía no vinculó ningún teléfono')
        ->assertNoAccessibilityIssues(1);
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

it('takes the person to the steps of linking their phone', function (): void {
    visitPage('attendance/portal', $this->path)
        ->press('Vincular este teléfono')
        ->assertSee('Paso 1 de 4')
        ->assertSee('Acuerdo de uso del teléfono');
});
