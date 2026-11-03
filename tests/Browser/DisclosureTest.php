<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\PublishPolicy;
use App\Modules\Disclosure\Actions\SaveDraft;
use App\Modules\Disclosure\Actions\StartPolicyDraft;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

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

/** A policy published to the only center of the company the user signed in to; returns the id of that version. */
function publishedPolicyForBrowser(): string
{
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    workIn($tenant);
    $draft = resolve(StartPolicyDraft::class)->handle(null);
    resolve(SaveDraft::class)->handle($draft->id, array_map(fn (array $section): array => [
        'key' => $section['key'],
        'text' => in_array($section['key'], ['responsibles', 'resources'], true) ? 'La dirección de Recursos Humanos.' : $section['text'],
        'items' => $section['items'],
    ], $draft->content));
    $user = App\Models\User::query()->firstOrFail();

    return resolve(PublishPolicy::class)->handle($draft->id, null, ['a', 'b', 'c'], true, $user->id)->id;
}

it('shows the board with the center that has no policy yet', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create(['name' => 'Planta Querétaro']);

    visitPage('disclosure/index', "{$url}/disclosure", $dark, $mobile)
        ->assertSee('Política y difusión')
        ->assertSee('Planta Querétaro')
        ->assertSee('Aún no hay una política publicada para este centro.')
        ->assertSee('Falta');
})->with($looks);

it('shows the board with the center the policy reached', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    publishedPolicyForBrowser();

    visitPage('disclosure/index', "{$url}/disclosure", $dark, $mobile)
        ->assertSee('Cumple')
        ->assertSee('Versión 1, publicada el');
})->with($looks);

it('shows the version in force and its history', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    publishedPolicyForBrowser();

    visitPage('disclosure/policy', "{$url}/disclosure/policy", $dark, $mobile)
        ->assertSee('Versión 1')
        ->assertSee('Historial de versiones')
        ->assertSee('Nueva versión desde la vigente');
})->with($looks);

it('reads a published version', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $version = publishedPolicyForBrowser();

    visitPage('disclosure/policy-version', "{$url}/disclosure/policy/versions/{$version}", $dark, $mobile)
        ->assertSee('La dirección de Recursos Humanos.')
        ->assertSee('Compromisos');
})->with($looks);

it('publishes the draft from the dialog', function (): void {
    $url = signInToPanel();
    WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create();

    visit("{$url}/disclosure/policy")
        ->press('Redactar a partir de la plantilla')
        ->fill('#section-responsibles-text', 'La dirección de Recursos Humanos.')
        ->fill('#section-resources-text', 'Un presupuesto anual.')
        ->press('Guardar borrador')
        ->assertSee('Borrador guardado.')
        ->press('Publicar')
        ->assertSee('Publicar la política')
        ->check('input[name="reviewed"]')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Política publicada: versión 1.')
        ->assertSee('Historial de versiones');
});
