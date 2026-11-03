<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\StartPolicyDraft;
use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('offers to write the policy from the template when there is none', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('disclosure/policy', "{$url}/disclosure/policy", $dark, $mobile)
        ->assertSee('Política de prevención')
        ->assertSee('Aún no hay un borrador')
        ->assertSee('Redactar a partir de la plantilla');
})->with($looks);

it('writes the draft: starts it from the template and saves what HR writes', function (): void {
    $url = signInToPanel();

    visit("{$url}/disclosure/policy")
        ->press('Redactar a partir de la plantilla')
        ->assertSee('Compromisos')
        ->assertSee('Es obligación de supervisores, gerentes y directores')
        ->fill('#section-responsibles-text', 'La dirección de Recursos Humanos.')
        ->press('Guardar borrador')
        ->assertSee('Borrador guardado.')
        ->assertValue('#section-responsibles-text', 'La dirección de Recursos Humanos.');
});

it('shows the draft sections to edit', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    workIn(Tenant::query()->where('slug', 'acme')->firstOrFail());
    resolve(StartPolicyDraft::class)->handle(null);

    visitPage('disclosure/policy', "{$url}/disclosure/policy", $dark, $mobile)
        ->assertSee('Responsables')
        ->assertSee('Recursos')
        ->assertSee('Principio 1')
        ->assertSee('Guardar borrador');
})->with($looks);
