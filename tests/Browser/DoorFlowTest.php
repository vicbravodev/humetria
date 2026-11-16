<?php

declare(strict_types=1);

use App\Modules\Demos\Actions\CloseDoor;
use App\Modules\Demos\Actions\OpenDoor;
use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

/**
 * The demo company with only the first door person of the real pool, and its door open: the address of its QR.
 */
function openDemoDoor(): string
{
    $demo = Tenant::factory()->demo()->create(['slug' => 'alba', 'trade_name' => 'Manufacturas Alba del Bajío']);
    Employee::factory()->create(['id' => resolve(DemoDoorPool::class)->employeeIds()[0], 'tenant_id' => $demo->id, 'name' => 'Ana Solís']);

    return (string) parse_url(resolve(OpenDoor::class)->handle()['url'], PHP_URL_PATH);
}

it('takes a visitor from the QR to the portal of their own demo worker, on the phone', function (): void {
    visitPage('telework/portal', openDemoDoor(), mobile: true)
        ->assertSee('Ana Solís')
        ->assertNoJavaScriptErrors();
});

it('tells a visitor the room is full', function (bool $dark, bool $mobile): void {
    $door = openDemoDoor();
    // Someone else took the only worker, from another phone.
    $this->get($door)->assertRedirect();

    visitPage('demos/door', $door, $dark, $mobile)
        ->assertSee('La sala está llena')
        ->assertSee('Demostración en vivo');
})->with($looks);

it('tells a visitor the door is closed, without saying why', function (bool $dark, bool $mobile): void {
    $door = openDemoDoor();
    resolve(CloseDoor::class)->handle();

    visitPage('demos/door', $door, $dark, $mobile)
        ->assertSee('Este código ya no está abierto');
})->with($looks);
