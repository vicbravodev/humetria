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

/** The personal link to the policy of an employee the published policy asked; returns its address. */
function personalPolicyUrl(): string
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $center = WorkCenter::factory()->for($tenant)->create();
    $ana = App\Modules\Organization\Models\Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    $hr = memberOf($tenant, App\Modules\Tenancy\Enums\TenantRole::Hr);
    workIn($tenant);
    $draft = resolve(StartPolicyDraft::class)->handle(null);
    resolve(SaveDraft::class)->handle($draft->id, array_map(fn (array $section): array => [
        'key' => $section['key'],
        'text' => in_array($section['key'], ['responsibles', 'resources'], true) ? 'La dirección de Recursos Humanos.' : $section['text'],
        'items' => $section['items'],
    ], $draft->content));
    resolve(PublishPolicy::class)->handle($draft->id, null, ['a', 'b', 'c'], true, $hr->id);

    return route('portal.policy.show', resolve(App\Modules\Telework\Contracts\PortalAccess::class)->token($ana->id), absolute: false);
}

it('shows a person the policy of their center to read and confirm', function (bool $dark, bool $mobile): void {
    visitPage('disclosure/portal-policy', personalPolicyUrl(), $dark, $mobile)
        ->assertSee('La política de prevención, Ana Ruiz')
        ->assertSee('Compromisos')
        ->assertSee('Confirmo que la leí');
})->with($looks);

it('lets the person confirm from the phone, once', function (): void {
    visit(personalPolicyUrl())->on()->mobile()
        ->press('Confirmo que la leí')
        ->assertSee('Gracias. Quedó registrado que leyó la política.')
        ->assertSee('Usted confirmó el')
        ->assertDontSee('Confirmo que la leí');
});

/** The company the user signed in to has a published policy and `$people` people asked to confirm it; one has confirmed. */
function askedForBrowser(int $people = 3): void
{
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $center = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    workIn($tenant);
    $draft = resolve(StartPolicyDraft::class)->handle(null);
    resolve(SaveDraft::class)->handle($draft->id, array_map(fn (array $section): array => [
        'key' => $section['key'],
        'text' => in_array($section['key'], ['responsibles', 'resources'], true) ? 'La dirección de Recursos Humanos.' : $section['text'],
        'items' => $section['items'],
    ], $draft->content));
    $user = App\Models\User::query()->firstOrFail();
    $staff = staffOf($tenant, $center, $people);
    $policy = resolve(PublishPolicy::class)->handle($draft->id, null, ['a', 'b', 'c'], true, $user->id);
    App\Modules\Disclosure\Models\Acknowledgment::query()->where('document_id', $policy->id)->where('employee_id', $staff[0]->id)->update(['channel' => 'portal', 'acknowledged_at' => now()]);
}

it('follows who confirmed the policy', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    askedForBrowser();

    visitPage('disclosure/dissemination', "{$url}/disclosure/dissemination", $dark, $mobile)
        ->assertSee('Difusión de la política')
        ->assertSee('1 de 3 personas confirmaron')
        ->assertSee('Pendientes')
        ->assertSee('Recordar a los pendientes');
})->with($looks);

it('records paper acknowledgments of the people ticked', function (): void {
    $url = signInToPanel();
    askedForBrowser(2);

    visit("{$url}/disclosure/dissemination?status=pending")
        ->click('[role="checkbox"]')
        ->press('Registrar acuses en papel')
        ->fill('#paper-note', 'Hoja 1')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Se registró 1 acuse en papel.');
});
