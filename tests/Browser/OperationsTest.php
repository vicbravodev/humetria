<?php

declare(strict_types=1);

use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Tenancy\Models\Tenant;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

beforeEach(function (): void {
    test()->mock(MasterSupervisorRepository::class)->shouldReceive('all')->andReturn([(object) ['status' => 'running']]);
    test()->mock(WorkloadRepository::class)->shouldReceive('get')->andReturn([['name' => 'default', 'length' => 2, 'wait' => 3]]);
});

it('shows the superadmin how the platform is running', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Delivery::factory()->mail()->create(['status' => DeliveryStatus::Delivered]);

    visitPage('operations/health', '/admin/operations/health', $dark, $mobile)
        ->assertSee('Salud del sistema')
        ->assertSee('Colas (Horizon)')
        ->assertSee('Programador de tareas')
        ->assertSee('Sin latido')
        ->assertSee('Correos hoy')
        ->assertSee('Ningún trabajo falló en los últimos 7 días.');
})->with($looks);

it('lays out health as its board: the hours of mail and WhatsApp, the scheduler and the active alerts beside', function (): void {
    signInAsSuperadmin();
    Delivery::factory()->count(2)->create(['status' => DeliveryStatus::Failed]);

    visitPage('operations/health', '/admin/operations/health')
        ->assertSee('Abrir PostHog')
        ->assertSee('Últimas 24 horas · una barra por hora')
        ->assertPresent('[role="img"][aria-label="Últimas 24 horas: ninguna hora pasó del 5 % de rechazos."]')
        ->assertPresent('[role="img"][aria-label="Últimas 24 horas: 1 hora pasó del 5 % de rechazos."]')
        ->assertSee('Latido del programador')
        ->assertSee('Alertas activas')
        ->assertSee('Programador de tareas: sin latido');
});

it('shows the superadmin the messaging of the whole platform, never a message', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Textiles del Norte']);
    Delivery::factory()->count(5)->create(['tenant_id' => $tenant->id, 'status' => DeliveryStatus::Failed]);
    Delivery::factory()->create(['status' => DeliveryStatus::Read]);

    visitPage('operations/messaging', '/admin/operations/messaging', $dark, $mobile)
        ->assertSee('Mensajería')
        ->assertSee('Plantillas de WhatsApp')
        ->assertSee('Invitación a la evaluación NOM-035 · v1')
        ->assertSee('Textiles del Norte')
        ->assertSee('Conexión con Meta')
        ->assertSee('Rechazos hoy');
})->with($looks);
