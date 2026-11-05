<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;

// Board V2-Ruta-N037: from the route of a center to what an inspector would ask for, and to Humi.
it('goes from the route of a center to the inspection and opens Humi from its card', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id])->id]);
    test()->actingAs(ownerOf($tenant));

    visit("/admin/acme/telework/centers/{$plant->id}/route")
        ->assertSee('Podrías mostrar')
        ->click('Pregúntale a Humi')
        ->assertSee('Hola, soy Humi')
        ->assertNoJavaScriptErrors();

    visit("/admin/acme/telework/centers/{$plant->id}/route")
        ->click('Ver lo que te pediría')
        ->assertSee('Si hoy llegara una inspección a Planta Querétaro')
        ->assertNoJavaScriptErrors();
});
