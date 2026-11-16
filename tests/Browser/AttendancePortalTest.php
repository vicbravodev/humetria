<?php

declare(strict_types=1);

use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Database\Factories\Organization\EmployeeFactory;

/** «Mi jornada» in the portal: one place for marking, the week and the keys, light and dark, on a desktop and on a phone. */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->path = '/portal/'.resolve(IssuePortalLink::class)->handle($this->ana->id).'/attendance';
});

it('tells a person with no key to ask RH for their link, and offers marking and the week', function (bool $dark, bool $mobile): void {
    visitPage('attendance/portal', $this->path, $dark, $mobile)
        ->assertSee('Mi jornada')
        ->assertSee('Ana López')
        ->assertSee('Marcar')
        ->assertSee('Mi semana')
        ->assertSee('Todavía no tiene una llave de acceso')
        ->assertSee('Pida a Recursos Humanos su enlace para crearla')
        ->assertDontSee('Agregar una llave en este teléfono')
        ->assertSee('Volver a mi portal')
        ->assertNoAccessibilityIssues(1);
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('lists the keys of the person and leads them to their entrance to add another', function (bool $dark, bool $mobile): void {
    WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone de Ana', 'registered_at' => '2026-10-01 12:00:00']);

    visitPage('attendance/portal', $this->path, $dark, $mobile)
        ->assertSee('iPhone de Ana')
        ->assertSee('Registrada el')
        ->assertSee('Agregar una llave en este teléfono')
        ->assertDontSee('Todavía no tiene una llave de acceso')
        ->assertNoAccessibilityIssues(1);
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

it('takes a person with a key to their entrance, where they add another', function (): void {
    WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone de Ana']);

    visitPage('attendance/portal', $this->path)
        ->click('Agregar una llave en este teléfono')
        ->waitForText('Entrar con mi huella')
        ->assertPathIs('/me/acme');
});
