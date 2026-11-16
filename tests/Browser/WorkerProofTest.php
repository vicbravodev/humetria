<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Notification;

/*
 * The step to prove it is them before something delicate (CUJ 5), for a person without a key: a code by email.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
});

it('asks a person who entered with a link to confirm it is them with a code, and then takes them on', function (bool $dark, bool $mobile): void {
    $page = visitPage('worker-access/proof', '/me/acme/proof', $dark, $mobile, enteringTo($this->ana->id, 'Confirme que es usted'))
        ->assertSee('Enviarme un código')
        ->assertDontSee('Confirmar con mi huella')
        ->assertNoAccessibilityIssues(1);

    provedWithCode($page, 'Hola, Ana López');
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

it('tells a person without a key or an email to ask RH for their access', function (): void {
    App\Modules\Organization\Models\Employee::query()->whereKey($this->ana->id)->update(['email' => null]);

    visitPage('worker-access/proof', '/me/acme/proof', reach: enteringTo($this->ana->id, 'Confirme que es usted'))
        ->assertSee('Pida su acceso a Recursos Humanos')
        ->assertDontSee('Enviarme un código');
});
