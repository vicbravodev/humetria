<?php

declare(strict_types=1);

use App\Modules\Demos\Models\DemoRequest;

it('puts what needs attention first, each with the way to act on it', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    DemoRequest::factory()->create(['created_at' => now()->subHours(49)]);

    visitPage('tenancy/overview', '/admin/overview', $dark, $mobile)
        ->assertSee('Atiende primero')
        ->assertSee('1 solicitud de demo lleva más de 48 h sin respuesta')
        ->click('Responder')
        ->assertPathIs('/admin/demo-requests');
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

it('says all is calm when nothing needs attention', function (): void {
    signInAsSuperadmin();

    visitPage('tenancy/overview', '/admin/overview')
        ->assertSee('Todo en orden: nada espera tu atención hoy.');
});

it('says nothing was billed yet instead of drawing empty columns', function (): void {
    signInAsSuperadmin();

    visitPage('tenancy/overview', '/admin/overview')
        ->assertSee('Todavía no hay estados de cuenta emitidos en el último año.');
});
