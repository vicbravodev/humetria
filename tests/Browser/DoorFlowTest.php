<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Demos\Actions\CloseDoor;
use App\Modules\Demos\Actions\OpenDoor;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;

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
    // The pool finds its people in the company by their employee number (their ids are new on every prepare).
    $first = array_values(array_filter(DemoRoster::people(), fn (DemoPerson $person): bool => $person->reservedForDoor))[0];
    Employee::factory()->create(['tenant_id' => $demo->id, 'name' => 'Ana Solís', 'employee_number' => $first->employeeNumber]);
    // The demo's head of HR issues the links the door hands out.
    User::factory()->create(['email' => DemoRoster::person(DemoRoster::HR)->email]);

    return (string) parse_url(resolve(OpenDoor::class)->handle()['url'], PHP_URL_PATH);
}

it('takes a visitor from the QR into their own demo worker, on the phone, to create their key', function (): void {
    visitPage('worker-access/link', openDemoDoor(), mobile: true)
        ->press('Continuar')
        ->waitForText('Ana Solís, cree su llave de acceso')
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
