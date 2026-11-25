<?php

declare(strict_types=1);

use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
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
