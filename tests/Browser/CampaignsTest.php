<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\WorkCenter;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** Signs in an hr of the company at /admin/acme and returns it. */
function campaignsCompany(): Tenant
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    TenantMembership::factory()->for($tenant)->for($user)->role(TenantRole::Hr)->create();
    test()->actingAs($user);

    return $tenant;
}

it('lists the work centers to evaluate before any evaluation', function (bool $dark, bool $mobile): void {
    $tenant = campaignsCompany();
    staffOf($tenant, WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']), 20);

    visitPage('campaigns/index', '/admin/acme/campaigns', $dark, $mobile)
        ->assertSee('Planta Norte')
        ->assertSee('Manufactura de autopartes')
        ->assertSee('20 personas')
        ->assertSee('I y II')
        ->assertSee('De 16 a 50')
        ->assertSee('Sin evaluar')
        ->assertSee('Qué guía aplica según el tamaño del centro')
        ->assertSee('Evaluar');
})->with($looks);

it('follows a running evaluation and the history from each work center', function (bool $dark, bool $mobile): void {
    Illuminate\Support\Facades\Notification::fake();
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    staffOf($tenant, $plant, 20);
    $this->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id]);
    $this->post(route('tenant.campaigns.launch', [$tenant, Campaign::query()->withoutGlobalScopes()->sole()->id]), ['closes_on' => now()->addWeeks(2)->toDateString()]);
    $warehouse = WorkCenter::factory()->for($tenant)->create(['name' => 'Almacén']);
    staffOf($tenant, $warehouse, 3);
    Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $warehouse->id]);

    visitPage('campaigns/index', '/admin/acme/campaigns', $dark, $mobile)
        ->assertSee('En curso')
        ->assertSee('0 de 20')
        ->assertSee('Ver avance')
        ->assertSee('Al día')
        ->assertSee('Ver resultados')
        ->assertSee('Historial');
})->with($looks);

it('opens the new-evaluation assistant at its second step from a work center', function (bool $dark, bool $mobile): void {
    $tenant = campaignsCompany();
    staffOf($tenant, WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']), 60);

    visitPage('campaigns/index', '/admin/acme/campaigns', $dark, $mobile, fn ($page) => $page
        ->click('button:has-text("Evaluar")')
        ->assertSee('Nueva evaluación')
        ->assertSee('A quién se aplica la Guía III'));
})->with($looks);

it('pages the evaluations and the work centers', function (): void {
    $tenant = campaignsCompany();

    foreach (range(1, 26) as $index) {
        WorkCenter::factory()->for($tenant)->create(['name' => sprintf('Centro %02d', $index)]);
    }

    visit('/admin/acme/campaigns')
        ->assertSee('Centro 25')
        ->assertDontSee('Centro 26')
        ->click('[aria-label="Páginas de centros de trabajo"] >> text=Siguientes')
        ->assertSee('Centro 26')
        ->assertQueryStringHas('centers', '2')
        ->assertNoJavaScriptErrors();
});

it('shows a sampled draft with who was drawn', function (bool $dark, bool $mobile): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    staffOf($tenant, $plant, female: 30, male: 30);
    $this->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id, 'population' => 'sample']);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();

    visitPage('campaigns/show', "/admin/acme/campaigns/{$campaign->id}", $dark, $mobile)
        ->assertSee('Planta Norte')
        ->assertSee('Borrador')
        ->assertSee('Muestra de 53: Mujeres 27 · Hombres 26')
        ->assertSee('Muestra sorteada (53)')
        ->assertSee('Página 1 de 3');
})->with($looks);

it('prepares an evaluation from the panel, reached from the sidebar', function (): void {
    $tenant = campaignsCompany();
    staffOf($tenant, WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']), 60);

    visit('/admin/acme')
        ->click('NOM-035')
        ->assertPathIs('/admin/acme/campaigns')
        ->click('button:has-text("Evaluar")')
        ->assertSee('Planta Norte · 60 personas')
        ->assertSee('Con más de 50 personas aplican las Guías I y III.')
        ->assertSee('Muestra representativa · 53 personas')
        ->click('button:has-text("Siguiente: fecha y envío")')
        ->assertSee('Borrador de evaluación creado.')
        ->assertSee('Muestra sorteada (53)')
        ->assertNoJavaScriptErrors();

    expect(Campaign::query()->withoutGlobalScopes()->sole()->sample_size)->toBe(53);
});

it('chooses the work center in the new-evaluation assistant, and everyone instead of a sample', function (): void {
    $tenant = campaignsCompany();
    staffOf($tenant, WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']), 60);

    visit('/admin/acme/campaigns')
        ->click('Nueva evaluación')
        ->click('#evaluation-work-center')
        ->type('[role="combobox"][aria-controls="evaluation-work-center-options"]', 'norte')
        ->click('[role="option"]:has-text("Planta Norte")')
        ->click('button:has-text("Siguiente: guías y muestra")')
        ->assertSee('Planta Norte · 60 personas')
        ->click('Todas las personas · 60')
        ->click('button:has-text("Siguiente: fecha y envío")')
        ->assertSee('Borrador de evaluación creado.')
        ->assertNoJavaScriptErrors();

    expect(Campaign::query()->withoutGlobalScopes()->sole()->population->value)->toBe('all');
});

it('launches a draft with the deadline suggested, inviting everyone by email', function (): void {
    Illuminate\Support\Facades\Notification::fake();
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    staffOf($tenant, $plant, 20);
    $this->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();

    visit("/admin/acme/campaigns/{$campaign->id}")
        ->assertSee('Último día para contestar')
        ->assertSee('Así le llega a cada persona')
        ->click('Lanzar e invitar a 20 personas')
        ->assertSee('Evaluación lanzada. Las invitaciones van en camino.')
        ->assertSee('En curso')
        ->assertSee('0 de 20')
        ->assertDontSee('Último día para contestar')
        ->assertNoJavaScriptErrors();

    Illuminate\Support\Facades\Notification::assertSentOnDemandTimes(App\Modules\Campaigns\Notifications\SurveyInvitation::class, 20);
});

it('follows a launched evaluation: answers per guide, the sample and who answered', function (bool $dark, bool $mobile): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    staffOf($tenant, $plant, female: 30, male: 30);
    Illuminate\Support\Facades\Notification::fake();
    $this->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id, 'population' => 'sample']);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();
    $this->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => now()->addWeek()->toDateString()]);
    App\Modules\Campaigns\Models\ParticipantInstrument::query()->withoutGlobalScopes()->where('instrument', 'nom035.guide-iii')->limit(10)->update(['status' => 'completed', 'completed_at' => now()]);

    visitPage('campaigns/show', "/admin/acme/campaigns/{$campaign->id}", $dark, $mobile)
        ->assertSee('En curso')
        ->assertSee('Faltan 43 respuestas para el mínimo de la muestra')
        ->assertSee('10 de 53')
        ->assertSee('43 pendientes')
        ->assertSee('Participación por área')
        ->assertSeeIn('[aria-label="Situación de las personas"] [aria-current="page"]', 'Pendientes')
        ->assertSeeIn('[aria-label="Situación de las personas"] [aria-current="page"]', '60')
        ->assertSee('Página 1 de 3');
})->with($looks);

it('narrows who answered a guide and searches them by name', function (): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $people = staffOf($tenant, $plant, female: 30)->values();
    $people[0]->update(['name' => 'Zoila Contreras', 'area_id' => App\Modules\Organization\Models\Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Calidad'])->id]);
    Illuminate\Support\Facades\Notification::fake();
    $this->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();
    $this->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => now()->addWeek()->toDateString()]);
    $zoila = App\Modules\Campaigns\Models\Participant::query()->withoutGlobalScopes()->where('employee_id', $people[0]->id)->sole();
    App\Modules\Campaigns\Models\ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $zoila->id)->update(['status' => 'completed', 'completed_at' => now()]);

    visit("/admin/acme/campaigns/{$campaign->id}")
        ->assertSeeIn('[aria-label="Situación de las personas"] [aria-current="page"]', '29')
        ->assertDontSee('Zoila Contreras')
        ->click('[aria-label="Situación de las personas"] >> text=Contestaron')
        ->assertSeeIn('[aria-label="Situación de las personas"] [aria-current="page"]', '1')
        ->assertSee('Zoila Contreras')
        ->click('[aria-label="Situación de las personas"] >> text=Todas')
        ->assertSeeIn('[aria-label="Situación de las personas"] [aria-current="page"]', '30')
        ->click('#filter-area')
        ->type('[role="combobox"][aria-controls="filter-area-options"]', 'cali')
        ->assertSeeIn('#filter-area-options', 'Calidad')
        ->keys('[role="combobox"][aria-controls="filter-area-options"]', 'Enter')
        ->assertQueryStringHas('area')
        ->assertSee('Zoila Contreras')
        ->type('[aria-label="Buscar por nombre, correo o número"]', 'zoila')
        ->assertQueryStringHas('q', 'zoila')
        ->assertSee('Zoila Contreras')
        ->assertNoJavaScriptErrors();
});

it('shows how the sending of the links goes until it ends, and offers to resend to whom it failed', function (bool $dark, bool $mobile): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->active()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'invitation_batch' => 'a31a4b00-0000-4000-8000-000000000002']);

    foreach (['sent', 'sent', 'failed', 'queued'] as $status) {
        $participant = App\Modules\Campaigns\Models\Participant::factory()->create(['campaign_id' => $campaign->id, 'invitation_batch' => $campaign->invitation_batch, 'invitation_status' => $status]);
        App\Modules\Campaigns\Models\ParticipantInstrument::factory()->create(['participant_id' => $participant->id]);
    }

    $page = visitPage('campaigns/show', "/admin/acme/campaigns/{$campaign->id}", $dark, $mobile)
        ->assertSee('Enviando invitaciones · 3 de 4')
        ->assertSee('No se pudo enviar el enlace a 1 persona.');

    App\Modules\Campaigns\Models\Participant::query()->withoutGlobalScopes()->where('invitation_status', 'queued')->update(['invitation_status' => 'sent']);

    $page->wait(4)->assertDontSee('Enviando invitaciones');
})->with($looks);

it('sends the link again only to whom it failed', function (): void {
    Illuminate\Support\Facades\Notification::fake();
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->active()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

    foreach (['sent', 'failed', 'failed'] as $status) {
        $participant = App\Modules\Campaigns\Models\Participant::factory()->create(['campaign_id' => $campaign->id, 'invitation_status' => $status]);
        App\Modules\Campaigns\Models\ParticipantInstrument::factory()->create(['participant_id' => $participant->id]);
    }

    visit("/admin/acme/campaigns/{$campaign->id}")
        ->click('Ver y reenviar')
        ->assertSeeIn('[aria-label="Situación de las personas"] [aria-current="page"]', 'Envío fallido')
        ->assertSee('2 personas en esta lista')
        ->click('Enviar recordatorio')
        ->click('Reenviar enlaces')
        ->assertSee('Enlace reenviado a 2 personas.')
        ->assertNoJavaScriptErrors();

    Illuminate\Support\Facades\Notification::assertSentOnDemandTimes(App\Modules\Campaigns\Notifications\SurveyInvitation::class, 2);
});

it('sends links again, invites a newcomer and draws replacements from the evaluation page', function (): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    staffOf($tenant, $plant, female: 30, male: 30);
    Illuminate\Support\Facades\Notification::fake();
    $this->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id, 'population' => 'sample']);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();
    $this->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => now()->addWeek()->toDateString()]);
    staffOf($tenant, $plant, female: 1);

    visit("/admin/acme/campaigns/{$campaign->id}")
        ->click('Recordar a 60')
        ->assertSee('Es el mismo de antes: el que ya tienen sigue sirviendo.')
        ->click('Reenviar enlaces')
        ->assertSee('Enlace reenviado a 60 personas.')
        ->click('[aria-label="Más acciones"]')
        ->click('[role="menuitem"]:has-text("Invitar a quien falte")')
        // Nothing goes out before confirming: it says who gets a link.
        ->assertSee('¿Invitar a quien falte?')
        ->assertSee('quienes entraron al centro de trabajo después del lanzamiento')
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Se invitó a 1 persona.')
        ->assertSeeIn('[aria-label="Situación de las personas"] [aria-current="page"]', '61')
        ->click('[aria-label="Más acciones"]')
        ->click('[role="menuitem"]:has-text("Sortear reemplazos")')
        ->assertSee('del mismo género de quienes no contestarán')
        ->assertSee('¿De qué género son quienes no contestarán?')
        ->click('Hombres')
        ->clear('#replacements-count')
        ->type('#replacements-count', '2')
        ->click('Sortear e invitar')
        ->assertSee('Se sortearon 2 personas más para la muestra.')
        ->assertNoJavaScriptErrors();

    expect(App\Modules\Campaigns\Models\Participant::query()->withoutGlobalScopes()->where('in_sample', true)->count())->toBe(55);
});

it('extends the deadline and closes the evaluation, warning when the sample is short', function (): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    staffOf($tenant, $plant, female: 60);
    Illuminate\Support\Facades\Notification::fake();
    $this->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id, 'population' => 'sample']);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();
    $this->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => now()->addWeek()->toDateString()]);

    visit("/admin/acme/campaigns/{$campaign->id}")
        ->click('Extender fecha')
        ->assertSee('Ahora cierra el '.App\Modules\Campaigns\Support\CampaignClock::day(now()->addWeek(), isDate: true).'.')
        ->assertDontSee('Hoy cierra el')
        ->click('Cancelar')
        ->click('[aria-label="Más acciones"]')
        ->click('[role="menuitem"]:has-text("Cerrar evaluación")')
        ->assertSee('Faltan 53 respuestas para el mínimo de la muestra que pide la norma.')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Evaluación cerrada. Los enlaces ya no funcionan.')
        ->assertSee('Cerrada')
        ->assertSee('Historial')
        ->assertDontSee('Recordar a 60')
        ->assertNoJavaScriptErrors();

    expect($campaign->refresh()->status->value)->toBe('closed');
});

it('shows a closed evaluation: how many answered, the Guía I channeling and its history', function (bool $dark, bool $mobile): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

    foreach (['completed', 'completed', 'pending'] as $status) {
        $participant = App\Modules\Campaigns\Models\Participant::factory()->create(['campaign_id' => $campaign->id]);
        App\Modules\Campaigns\Models\ParticipantInstrument::factory()->create(['participant_id' => $participant->id, 'instrument' => 'nom035.guide-ii', 'status' => $status, 'completed_at' => $status === 'completed' ? now() : null]);
    }

    visitPage('campaigns/show', "/admin/acme/campaigns/{$campaign->id}", $dark, $mobile)
        ->assertSee('Cerrada')
        ->assertSee('Participación final · Guía II')
        ->assertSee('67 %')
        ->assertSee('Ver canalización')
        ->assertSee('Siguiente evaluación')
        ->assertSee('Informe 7.7');
})->with($looks);

it('applies Guía I to the people of an event', function (bool $dark, bool $mobile): void {
    Illuminate\Support\Facades\Notification::fake();
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $people = staffOf($tenant, $plant, 3)->values();
    $people[0]->update(['name' => 'Ana López']);

    visitPage('campaigns/trauma', "/admin/acme/campaigns/trauma?work_center={$plant->id}", $dark, $mobile)
        ->assertSee('trayecto directo')
        ->assertSee('Qué pasa después')
        ->assertSee('Solo personas de Planta Norte.')
        ->assertSee('Ana López');
})->with($looks);

it('keeps who was ticked across pages and searches, and ticks everyone a search found', function (): void {
    Illuminate\Support\Facades\Notification::fake();
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $people = staffOf($tenant, $plant, 30)->values();
    $people[0]->update(['name' => 'Aarón Abad']);
    $people[1]->update(['name' => 'Zacarías Qwértyz']);
    $people[2]->update(['name' => 'Zoé Qwértyz']);

    visit("/admin/acme/campaigns/trauma?work_center={$plant->id}")
        ->click('Aarón Abad')
        ->assertSee('(1 elegidas)')
        ->click('Siguientes')
        ->assertSee('Página 2 de 2')
        ->assertSee('(1 elegidas)')
        ->type('[aria-label="Buscar por nombre, correo o número"]', 'qwertyz')
        ->assertSee('Elegir a las 2 encontradas')
        ->click('Elegir a las 2 encontradas')
        ->assertSee('(3 elegidas)')
        ->assertSee('+2 más')
        ->assertSee('Enviar Guía I a 3 personas')
        ->click('button:has-text("Enviar Guía I")')
        ->assertSee('Guía I enviada a las personas elegidas.')
        ->assertNoJavaScriptErrors();

    expect(App\Modules\Campaigns\Models\Participant::query()->withoutGlobalScopes()->pluck('employee_id')->sort()->values()->all())
        ->toBe(collect([$people[0]->id, $people[1]->id, $people[2]->id])->sort()->values()->all());
});

it('sends Guía I to the people ticked, from the NOM-035 page', function (): void {
    Illuminate\Support\Facades\Notification::fake();
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $people = staffOf($tenant, $plant, 3)->values();
    $people[0]->update(['name' => 'Ana López']);

    visit('/admin/acme/campaigns')
        ->click('Guía I por acontecimiento')
        ->click('#trauma-work-center')
        ->type('[role="combobox"][aria-controls="trauma-work-center-options"]', 'norte')
        ->assertSee('Planta Norte')
        ->keys('[role="combobox"][aria-controls="trauma-work-center-options"]', 'Enter')
        ->assertSee('Ana López')
        ->click('Ana López')
        ->assertSee('(1 elegidas)')
        ->click('button:has-text("Enviar Guía I")')
        ->assertSee('Guía I enviada a las personas elegidas.')
        ->assertSee('Guía I por acontecimiento')
        ->assertNoJavaScriptErrors();

    expect(App\Modules\Campaigns\Models\Participant::query()->withoutGlobalScopes()->sole()->employee_id)->toBe($people[0]->id);
});

it('shows the results of a closed evaluation by risk level, and filters them by area', function (bool $dark, bool $mobile): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

    foreach (['Producción' => ['never', 6], 'Ventas' => ['always', 5]] as $name => [$value, $count]) {
        $area = App\Modules\Organization\Models\Area::factory()->create(['tenant_id' => $tenant->id, 'name' => $name]);
        App\Modules\Responses\Models\AnonymousResponse::factory()->count($count)->create(['campaign_id' => $campaign->id, 'area_id' => $area->id, 'answers' => nom035Answers('nom035.guide-ii', $value)]);
    }

    visitPage('results/show', "/admin/acme/campaigns/{$campaign->id}/results", $dark, $mobile)
        ->assertSee('Calificación final')
        ->assertSee('Liderazgo y relaciones en el trabajo')
        ->assertSee('Producción')
        ->assertSee('Qué pide la norma en cada nivel')
        ->assertSee('Guía I · acontecimientos traumáticos');
})->with($looks);

it('gives each question and each level of the norm room to read at every width', function (): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
    App\Modules\Responses\Models\AnonymousResponse::factory()->count(6)->create(['campaign_id' => $campaign->id, 'answers' => nom035Answers('nom035.guide-ii', 'always')]);
    $page = visitPage('results/show', "/admin/acme/campaigns/{$campaign->id}/results");
    // The narrowest column of words among the questions and the levels' actions, in px.
    $narrowest = '() => Math.min(...Array.from(document.querySelectorAll(\'ol > li > p:first-child, dl dd\')).filter((text) => text.offsetWidth > 0).map((text) => text.getBoundingClientRect().width)) >= 240';

    foreach ([375, 768, 1024, 1280] as $width) {
        resizeTo($page, $width)->assertScript($narrowest);
    }
});

it('narrows the results to one area, and reaches them from the evaluation', function (): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

    foreach (['Producción' => 6, 'Ventas' => 5] as $name => $count) {
        $area = App\Modules\Organization\Models\Area::factory()->create(['tenant_id' => $tenant->id, 'name' => $name]);
        App\Modules\Responses\Models\AnonymousResponse::factory()->count($count)->create(['campaign_id' => $campaign->id, 'area_id' => $area->id, 'answers' => nom035Answers('nom035.guide-ii')]);
    }

    visit("/admin/acme/campaigns/{$campaign->id}")
        ->click('Ver resultados')
        ->assertPathIs("/admin/acme/campaigns/{$campaign->id}/results")
        ->assertSee('Calificación final · Guía II')
        ->assertSee('de 11 cuestionarios')
        ->click('#results-area')
        ->click('[role="option"] >> text=Ventas')
        ->assertSee('Calificación final · Guía II · Ventas')
        ->assertSee('de 5 cuestionarios')
        ->assertQueryStringHas('area')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('lists who requires a clinical assessment after their Guía I, reached from the results', function (bool $dark, bool $mobile): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->trauma()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
    $person = staffOf($tenant, $plant, 1)->first();
    $person->update(['name' => 'Ana López']);
    App\Modules\Responses\Models\TraumaScreening::factory()->create(['campaign_id' => $campaign->id, 'employee_id' => $person->id, 'requires_clinical_assessment' => true]);

    visitPage('results/screenings', "/admin/acme/campaigns/{$campaign->id}/results", $dark, $mobile, fn ($page) => $page->click('Ver canalización'))
        ->assertSee('Personas que requieren valoración clínica')
        ->assertSee('1 de 1 personas')
        ->assertSee('Ana López');
})->with($looks);

it('shows the 7.7 report of a closed evaluation, ready to print', function (bool $dark, bool $mobile): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
    App\Modules\Responses\Models\AnonymousResponse::factory()->count(5)->create(['campaign_id' => $campaign->id, 'answers' => nom035Answers('nom035.guide-ii')]);

    visitPage('results/report', "/admin/acme/campaigns/{$campaign->id}/results", $dark, $mobile, fn ($page) => $page->click('Informe 7.7'))
        ->assertSee('Diagnóstico de factores de riesgo psicosocial')
        ->assertSee('Datos del centro de trabajo')
        ->assertSee('Pendiente de capturar')
        ->assertSee('Imprimir o guardar PDF');
})->with($looks);

it('completes the report with what HR writes', function (): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

    visit("/admin/acme/campaigns/{$campaign->id}/report")
        ->click('Completar informe')
        ->fill('#report-conclusions', 'La mayoría queda en riesgo medio.')
        ->fill('#report-recommendations', 'Programa de intervención en carga de trabajo.')
        ->fill('#report-responsible_name', 'Dra. Laura Méndez')
        ->fill('#report-responsible_license', '12345678')
        ->press('Guardar')
        ->assertSee('Informe guardado.')
        ->assertSee('La mayoría queda en riesgo medio.')
        ->assertSee('Dra. Laura Méndez · Cédula profesional 12345678')
        ->assertDontSee('Pendiente de capturar')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('shares the report through a temporary link that opens with no account until it is revoked', function (): void {
    $tenant = campaignsCompany();
    $tenant->update(['trade_name' => 'Acme']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

    $page = visit("/admin/acme/campaigns/{$campaign->id}/report")
        ->click('Compartir')
        ->click('#share-duration')
        ->click('[role="option"]:has-text("15 minutos")')
        ->press('Crear enlace')
        ->assertSee('Cópialo ahora: no se vuelve a mostrar.')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
    $link = $page->value('#report-link');

    expect($link)->toContain('/report/');

    $page->navigate(parse_url($link, PHP_URL_PATH) ?: '')
        ->assertSee('Acme compartió este informe contigo')
        ->assertSee('Diagnóstico de factores de riesgo psicosocial');

    visit("/admin/acme/campaigns/{$campaign->id}/report")
        ->assertSee('Enlaces abiertos')
        ->click('Revocar')
        // Nothing is revoked before confirming.
        ->assertSee('¿Revocar este enlace?')
        ->click('Cancelar')
        ->assertDontSee('Enlace revocado.')
        ->click('Revocar')
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Enlace revocado.')
        ->assertDontSee('Enlaces abiertos')
        ->navigate(parse_url($link, PHP_URL_PATH) ?: '')
        ->assertSee('Este enlace ya no está disponible');
});

it('opens a shared report, or says the link is no longer available', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id]);
    App\Modules\Results\Models\ReportShare::factory()->create(['campaign_id' => $campaign->id, 'token_hash' => hash('sha256', str_repeat('a', 64))]);

    visitPage('results/shared-report', '/report/'.str_repeat('a', 64), $dark, $mobile)
        ->assertSee('Acme compartió este informe contigo')
        ->assertSee('Imprimir o guardar PDF');
    visitPage('results/shared-report', '/report/'.str_repeat('b', 64), $dark, $mobile)
        ->assertSee('Este enlace ya no está disponible')
        ->assertDontSee('Acme');
})->with($looks);

it('offers no new date once the last day has passed, but still lets hr close it', function (): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $campaign = Campaign::factory()->active()->create([
        'tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'closes_on' => now()->subDays(2)->toDateString(),
    ]);

    visit("/admin/acme/campaigns/{$campaign->id}")
        ->assertDontSee('Extender fecha')
        ->click('[aria-label="Más acciones"]')
        ->assertSee('Cerrar evaluación')
        ->assertNoJavaScriptErrors();
});
