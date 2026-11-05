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

// Rediseño v2 (board V2-N035-Politica, decisión A3): the policy is written with questions; the page keeps its version in
// force and its history.
it('offers to write the policy with questions when there is none', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('disclosure/policy', "{$url}/disclosure/policy", $dark, $mobile)
        ->assertSee('Política de prevención')
        ->assertSee('Aún no hay una política')
        ->assertSee('Escribir con preguntas');
})->with($looks);

it('writes the policy with questions, one at a time, with the text beside it', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('disclosure/policy-write', "{$url}/disclosure/write/policy", $dark, $mobile)
        ->assertSee('NOM-035 · Etapa 1 · Política de prevención')
        ->assertSee('Se guarda en cada respuesta')
        ->assertSee('Pregunta 1 de 6')
        ->assertSee('¿Quién firma la política en nombre de la empresa?')
        ->assertSee('Política de prevención de riesgos psicosociales')
        ->assertSee('La firma la dirección general.')
        ->assertSee('Texto de la Guía de referencia IV, listo para editar.')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with($looks);

it('keeps each answer and goes on to the next question', function (): void {
    $url = signInToPanel();

    visit("{$url}/disclosure/write/policy")
        ->radio('signature', 'hr')
        ->assertSee('La firma la dirección de Recursos Humanos.')
        ->click('Siguiente')
        ->assertSee('Pregunta 2 de 6')
        ->assertSee('¿Quién responde de aplicarla?')
        ->assertNoJavaScriptErrors();

    expect(App\Modules\Disclosure\Models\Document::query()->withoutGlobalScopes()->sole())
        ->answers->toMatchArray(['signature' => 'hr'])
        ->step->toBe(1);
});

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

// Rediseño v2 (board V4-Difusion): the six things of 5.7 × the centers, in place of the board of requirements and its tabs.
it('shows what the people must know, by center, with the next step', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create(['name' => 'Planta Querétaro']);

    visitPage('disclosure/index', "{$url}/disclosure", $dark, $mobile)
        ->assertSee('Lo que tus personas deben conocer')
        ->assertSee('1 de 6 listas')
        ->assertSee('Escribe tu política de prevención')
        ->assertSee('Planta Querétaro')
        ->assertSee('Medidas contra la violencia laboral')
        ->assertSee('Escribir · 5 min')
        ->assertSee('Página de las personas')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with($looks);

it('marks the policy published in the center it reached', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    publishedPolicyForBrowser();

    visitPage('disclosure/index', "{$url}/disclosure", $dark, $mobile)
        ->assertSee('Publicada, versión 1 del')
        ->assertSee('Sin difundir');
})->with($looks);

it('shows the version in force and its history', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    publishedPolicyForBrowser();

    visitPage('disclosure/policy', "{$url}/disclosure/policy", $dark, $mobile)
        ->assertSee('Versión 1')
        ->assertSee('Historial de versiones')
        ->assertSee('Nueva versión');
})->with($looks);

it('reads a published version', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $version = publishedPolicyForBrowser();

    visitPage('disclosure/policy-version', "{$url}/disclosure/policy/versions/{$version}", $dark, $mobile)
        ->assertSee('La dirección de Recursos Humanos.')
        ->assertSee('Compromisos');
})->with($looks);

it('publishes the policy from the end of its questions and goes back to the route of the center', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $center = WorkCenter::factory()->for($tenant)->create();
    workIn($tenant);
    resolve(App\Modules\Disclosure\Actions\SaveWriting::class)->handle(resolve(App\Modules\Disclosure\Support\Writing\Templates::class)->template(App\Modules\Disclosure\Enums\DocumentKind::Policy) ?? throw new LogicException, App\Modules\Disclosure\Enums\DocumentKind::Policy, [
        'signature' => 'hr', 'responsibles' => ['hr'], 'resources' => ['channel'], 'practices' => ['h'], 'principles' => ['p3'], 'dissemination' => ['page'],
    ], 6, App\Models\User::query()->firstOrFail()->id);

    visit("{$url}/disclosure/write/policy?center={$center->id}")
        ->assertSee('Tu política cubre lo que pide la norma')
        ->assertSee('Son responsables de aplicar esta política: Recursos Humanos.')
        ->press('Publicar')
        ->assertSee('Publicar la política')
        ->check('input[name="reviewed"]')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Política publicada: versión 1.')
        ->assertNoJavaScriptErrors();
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

// Rediseño v2 (board V4-Difusion-Registro): the evidence of the disclosure, with who confirmed by center on top.
it('follows who confirmed the policy, by center', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    askedForBrowser();

    visitPage('disclosure/dissemination', "{$url}/disclosure/dissemination", $dark, $mobile)
        ->assertSee('Cómo pruebas que lo difundiste')
        ->assertSee('Quién confirmó que leyó la política')
        ->assertSee('1 de 3 personas')
        ->assertSee('Planta Querétaro')
        ->assertSee('Recordar a 2 personas')
        ->assertSee('Resultados para las personas')
        ->assertSee('Pendientes')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with($looks);

it('says why a view of the confirmations is empty, and «Nadie coincide» only for a search', function (): void {
    $url = signInToPanel();
    askedForBrowser(1);

    visit("{$url}/disclosure/dissemination?status=pending")
        ->assertSee('Nadie tiene pendiente confirmar')
        ->assertDontSee('Nadie coincide')
        ->assertNoJavaScriptErrors();
    visit("{$url}/disclosure/dissemination?q=zzzz")
        ->assertSee('Nadie coincide');
});

it('registers paper signatures with how many signed and the scanned sheet', function (): void {
    $url = signInToPanel();
    askedForBrowser(1);

    visit("{$url}/disclosure/dissemination")
        ->press('Registrar firmas en papel')
        ->assertSee('Cuántas personas firmaron')
        ->assertSee('Arrastra la hoja escaneada aquí o elígela')
        ->assertNoJavaScriptErrors();
});

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
        ->assertSee('Aún no registras ninguno')
        ->click('section[aria-labelledby="media"] button:has-text("Cartel")')
        ->assertSee('Cómo se dio a conocer')
        ->assertSee('Arrastra la foto o el PDF aquí o elígelo')
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
        ->assertSee('Registrar otro');
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
        ->assertSee('Para las personas trabajadoras')
        ->assertSee('Nuestra política')
        ->assertSee('Es obligación de supervisores, gerentes y directores')
        ->assertSee('Para confirmar que la leyó, use el enlace personal que le llegó por correo.')
        ->assertSee('no guardamos quién visita esta página')
        ->assertSee('Otro centro')
        ->click('Leer completa')
        ->assertSee('Acme Manufacturas S.A. de C.V.');
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

it('writes a block with questions from its card', function (): void {
    $url = signInToPanel();

    visit("{$url}/disclosure/contents")
        ->click('section[aria-labelledby="block-violence_measures"] a:has-text("Escribir con preguntas")')
        ->assertSee('Pregunta 1 de 4')
        ->assertSee('¿Qué consideran violencia laboral?')
        ->assertNoJavaScriptErrors();
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
        ->assertSee('Resultados de la evaluación')
        ->assertSee('contestaron 12 personas')
        ->assertSee('Lo que más atención necesita')
        ->click('Ver todas las cifras')
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
        ->assertSee('¿Algo no está bien en su trabajo?')
        ->assertSee('Presentar una queja o denuncia')
        ->assertSee('Puede avisar sin dar su nombre.')
        ->click('Lo que hacemos para prevenir')
        ->assertSee('Del Programa de intervención')
        ->assertSee('Revisar las cargas de trabajo');
})->with($looks);

// Rediseño v2 (board V4-Carteles, decisiones A16 y D6): a letter sheet per center, printed with CSS.
it('shows the poster of a center with the address and its QR', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    workIn($tenant);
    resolve(App\Modules\Disclosure\Actions\IssuePageLink::class)->handle(App\Models\User::query()->firstOrFail()->id);

    visitPage('disclosure/poster', "{$url}/disclosure/page/poster?center={$plant->id}", $dark, $mobile)
        ->assertSee('Cartel para imprimir')
        ->assertSee('Lo que la empresa le informa sobre su bienestar')
        ->assertSee('Escanee con su celular')
        ->assertSee('No guardamos quién entra')
        ->assertDontSee('También ahí puede presentar una queja')
        ->assertSee('Imprimir el de Planta Querétaro')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth')
        ->assertScript('() => document.querySelector("img[alt^=\'Código QR\']").naturalWidth > 0', true);
})->with($looks);

it('prints the dossier of the disclosure of a center', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $center = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);

    visitPage('disclosure/dossier', "{$url}/disclosure/dossier/{$center->id}", $dark, $mobile)
        ->assertSee('Expediente de difusión · Planta Querétaro')
        ->assertSee('Política de prevención (5.1)')
        ->assertSee('Contenidos del numeral 5.7')
        ->assertSee('no certifica el cumplimiento de la norma');
})->with($looks);

// Rediseño v2 (board V4-Difusion-Contenidos, decisión A3): the blocks of 5.7 are written with questions, with the phone beside.
it('writes a block of 5.7 with questions, with how the people will read it on their phone', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('disclosure/content-write', "{$url}/disclosure/write/violence_measures", $dark, $mobile)
        ->assertSee('NOM-035 · numeral 5.7 b')
        ->assertSee('Medidas contra la violencia laboral')
        ->assertSee('Pregunta 1 de 4')
        ->assertSee('¿Qué consideran violencia laboral?')
        ->assertSee('Así lo verán en su celular')
        ->assertSee('Gritos, insultos o amenazas, humillaciones o burlas y acoso u hostigamiento.')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with($looks);

it('reviews the draft of the health effects and publishes it to the centers', function (): void {
    $url = signInToPanel();
    WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create();

    visit("{$url}/disclosure/write/health_effects")
        ->assertSee('Revisa el borrador antes de publicarlo')
        ->assertSee('Los factores de riesgo psicosocial son aquellos')
        ->press('Usar este borrador')
        ->press('Publicar en tus centros')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Publicado: versión 1.')
        ->assertNoJavaScriptErrors();
});
