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

it('opens the form to record a means of disclosure', function (): void {
    $url = signInToPanel();
    askedForBrowser(1);

    visit("{$url}/disclosure/dissemination")
        ->assertSee('Aún no registras ningún medio')
        ->press('Registrar un medio')
        ->assertSee('Cómo se dio a conocer')
        ->assertSee('Qué cubrió (numeral 5.7)')
        ->press('Cancelar')
        ->assertDontSee('Cómo se dio a conocer');
});

it('shows the physical means recorded', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    askedForBrowser(1);
    App\Modules\Disclosure\Models\DisseminationRecord::factory()->covering(['a', 'b'])->create(['tenant_id' => Tenant::query()->where('slug', 'acme')->firstOrFail()->id, 'work_center_id' => WorkCenter::query()->firstOrFail()->id, 'note' => 'Comedor']);

    visitPage('disclosure/dissemination', "{$url}/disclosure/dissemination", $dark, $mobile)
        ->assertSee('Medios físicos')
        ->assertSee('Cartel')
        ->assertSee('Registrar un medio');
})->with($looks);

it('offers to create the link of the page of the workers', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('disclosure/page', "{$url}/disclosure/page", $dark, $mobile)
        ->assertSee('Página de las personas')
        ->assertSee('Aún no hay enlace')
        ->assertSee('Crear el enlace');
})->with($looks);

it('shows the link, its state and what RH can do with it', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    resolve(App\Modules\Disclosure\Actions\IssuePageLink::class)->handle(App\Models\User::query()->firstOrFail()->id);

    visitPage('disclosure/page', "{$url}/disclosure/page", $dark, $mobile)
        ->assertSee('Activo')
        ->assertSee('Copiar enlace')
        ->assertSee('Ver como persona')
        ->assertSee('No vence: es una página permanente.')
        ->assertSee('Generar un enlace nuevo');
})->with($looks);

it('creates the link and replaces it from the page', function (): void {
    $url = signInToPanel();

    visit("{$url}/disclosure/page")
        ->press('Crear el enlace')
        ->assertSee('Enlace creado.')
        ->press('Generar un enlace nuevo')
        ->assertSee('El enlace actual deja de abrir al instante')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Enlace nuevo. El anterior ya no abre');
});

/** The address of the public page of the workers of a company with two centers, one with a published policy. */
function workersPageUrl(bool $policy = true): string
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'legal_name' => 'Acme Manufacturas S.A. de C.V.']);
    $hr = memberOf($tenant, App\Modules\Tenancy\Enums\TenantRole::Hr);
    $north = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Sur']);
    workIn($tenant);

    if ($policy) {
        $draft = resolve(StartPolicyDraft::class)->handle(null);
        resolve(SaveDraft::class)->handle($draft->id, array_map(fn (array $section): array => [
            'key' => $section['key'],
            'text' => in_array($section['key'], ['responsibles', 'resources'], true) ? 'La dirección de Recursos Humanos.' : $section['text'],
            'items' => $section['items'],
        ], $draft->content));
        resolve(PublishPolicy::class)->handle($draft->id, [$north->id], ['a', 'b', 'c'], true, $hr->id);
    }

    return route('workers.show', (string) resolve(App\Modules\Disclosure\Actions\IssuePageLink::class)->handle($hr->id)->token(), absolute: false);
}

it('shows the people the centers of their company to choose from', function (bool $dark, bool $mobile): void {
    visitPage('disclosure-public/centers', workersPageUrl(), $dark, $mobile)
        ->assertSee('¿En qué centro de trabajo está?')
        ->assertSee('Planta Norte')
        ->assertSee('Planta Sur');
})->with($looks);

it('shows the people the policy of their center', function (bool $dark, bool $mobile): void {
    $url = workersPageUrl();

    visitPage('disclosure-public/center', "{$url}/centers/".WorkCenter::query()->withoutGlobalScopes()->where('name', 'Planta Norte')->firstOrFail()->id, $dark, $mobile)
        ->assertSee('Planta Norte')
        ->assertSee('Política de prevención')
        ->assertSee('Acme Manufacturas S.A. de C.V.')
        ->assertSee('Es obligación de supervisores, gerentes y directores')
        ->assertSee('Otro centro de trabajo');
})->with($looks);

it('says a center has no policy yet', function (): void {
    $url = workersPageUrl(policy: false);

    visit("{$url}/centers/".WorkCenter::query()->withoutGlobalScopes()->where('name', 'Planta Sur')->firstOrFail()->id)
        ->assertSee('Este centro de trabajo todavía no publica su política de prevención.');
});

it('shows a link that does not open the page that says so and names nobody', function (bool $dark, bool $mobile): void {
    visitPage('disclosure-public/inactive', '/workers/'.str_repeat('a', 43), $dark, $mobile)
        ->assertSee('Este enlace ya no está activo')
        ->assertSee('Pídale a Recursos Humanos de su empresa el enlace vigente.')
        ->assertDontSee('Acme');
})->with($looks);

it('keeps the public page free of cookies and of policy violations', function (): void {
    visit(workersPageUrl())
        ->assertScript('() => document.cookie', '')
        ->assertScript(cspViolations(), '[]');
});

it('shows the blocks of 5.7 the company writes by hand', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('disclosure/contents', "{$url}/disclosure/contents", $dark, $mobile)
        ->assertSee('Contenidos para las personas')
        ->assertSee('Medidas contra la violencia laboral')
        ->assertSee('Posibles alteraciones a la salud')
        ->assertSee('Sin publicar');
})->with($looks);

it('writes a block, saves it and publishes it', function (): void {
    $url = signInToPanel();
    WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create();

    visit("{$url}/disclosure/contents")
        ->click('section[aria-labelledby="block-violence_measures"] button:has-text("Escribir")')
        ->assertSee('Guardar borrador')
        ->fill('#content-violence_measures', 'Las quejas las atiende la Comisión.')
        ->press('Guardar borrador')
        ->assertSee('Borrador guardado.')
        ->press('Publicar')
        ->assertSee('Publicar: Medidas contra la violencia laboral')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Publicado: versión 1.')
        ->assertSee('Las quejas las atiende la Comisión.');
});

it('shows the evaluations RH can publish to the people', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    [$campaign] = closedEvaluationInAreas($tenant, ['Producción' => 6, 'Ventas' => 6]);
    $campaign->update(['work_center_id' => $plant->id]);

    visitPage('disclosure/results', "{$url}/disclosure/results", $dark, $mobile)
        ->assertSee('Resultados para las personas')
        ->assertSee('Planta Querétaro')
        ->assertSee('Sin publicar')
        ->assertSee('Publicar');
})->with($looks);

it('publishes results and the people read them on their page, of the whole center', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    staffOf($tenant, $plant, 20);
    [$campaign] = closedEvaluationInAreas($tenant, ['Producción' => 6, 'Ventas' => 6]);
    $campaign->update(['work_center_id' => $plant->id]);
    resolve(App\Modules\Disclosure\Actions\PublishResults::class)->handle($campaign->id, App\Models\User::query()->firstOrFail()->id);
    $link = route('workers.center', [(string) resolve(App\Modules\Disclosure\Actions\IssuePageLink::class)->handle(App\Models\User::query()->firstOrFail()->id)->token(), $plant->id], absolute: false);

    visitPage('disclosure-public/center', $link, false, true)
        ->assertSee('Resultados de la identificación y análisis de los factores de riesgo psicosocial')
        ->assertSee('Calificación final (12 cuestionarios)')
        ->assertSee('no son un promedio');
});

it('shows the people the channel for complaints and the measures the company chose to show', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $hr = memberOf($tenant, App\Modules\Tenancy\Enums\TenantRole::Hr);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    staffOf($tenant, $plant, 20);
    workIn($tenant);
    App\Modules\Complaints\Models\ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
    [$campaign] = closedEvaluationInAreas($tenant, ['Producción' => 12]);
    $campaign->update(['work_center_id' => $plant->id]);
    $program = App\Modules\Interventions\Models\Program::factory()->create(['tenant_id' => $tenant->id, 'campaign_id' => $campaign->id]);
    $item = App\Modules\Interventions\Models\ProgramItem::factory()->create(['tenant_id' => $tenant->id, 'program_id' => $program->id]);
    App\Modules\Interventions\Models\ProgramAction::factory()->create(['tenant_id' => $tenant->id, 'program_id' => $program->id, 'item_id' => $item->id, 'title' => 'Revisar las cargas de trabajo']);
    resolve(App\Modules\Disclosure\Actions\PublishResults::class)->handle($campaign->id, $hr->id, showMeasures: true);
    $url = route('workers.center', [(string) resolve(App\Modules\Disclosure\Actions\IssuePageLink::class)->handle($hr->id)->token(), $plant->id], absolute: false);

    visitPage('disclosure-public/center', $url, $dark, $mobile)
        ->assertSee('Presentar una queja o denuncia')
        ->assertSee('El canal permite presentar la queja o la denuncia de forma anónima.')
        ->assertSee('Del Programa de intervención')
        ->assertSee('Revisar las cargas de trabajo');
})->with($looks);
