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
        ->assertSee('No hay evaluaciones abiertas')
        ->assertSee('Planta Norte')
        ->assertSee('NOM-035: Guías I y II')
        ->assertSee('Sin evaluar');
})->with($looks);

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
        ->assertSee('Muestra sorteada (53)');
})->with($looks);

it('prepares an evaluation from the panel, reached from the sidebar', function (): void {
    $tenant = campaignsCompany();
    staffOf($tenant, WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']), 60);

    visit('/admin/acme')
        ->click('NOM-035')
        ->assertPathIs('/admin/acme/campaigns')
        ->click('Nueva evaluación')
        ->click('#evaluation-work-center')
        ->click('[role="option"]:has-text("Planta Norte")')
        ->assertSee('¿Quién contesta la Guía III?')
        ->assertSee('Se sortean 53 de 60 personas')
        ->click('Una muestra representativa')
        ->click('Preparar borrador')
        ->assertSee('Borrador de evaluación creado.')
        ->assertSee('Muestra sorteada (53)')
        ->assertNoJavaScriptErrors();

    expect(Campaign::query()->withoutGlobalScopes()->sole()->sample_size)->toBe(53);
});

it('launches a draft with the deadline suggested, inviting everyone by email', function (): void {
    Illuminate\Support\Facades\Notification::fake();
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    staffOf($tenant, $plant, 20);
    $this->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();

    visit("/admin/acme/campaigns/{$campaign->id}")
        ->assertSee('Lanzar la evaluación')
        ->click('Lanzar e invitar')
        ->assertSee('Evaluación lanzada. Las invitaciones van en camino.')
        ->assertSee('En curso')
        ->assertSee('0 de 20')
        ->assertDontSee('Lanzar la evaluación')
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
        ->assertSee('Pendientes (60)')
        ->assertSee('Página 1 de 3');
})->with($looks);

it('narrows who answered a guide and searches them by name', function (): void {
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $people = staffOf($tenant, $plant, female: 30)->values();
    $people[0]->update(['name' => 'Zoila Contreras']);
    Illuminate\Support\Facades\Notification::fake();
    $this->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();
    $this->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => now()->addWeek()->toDateString()]);
    $zoila = App\Modules\Campaigns\Models\Participant::query()->withoutGlobalScopes()->where('employee_id', $people[0]->id)->sole();
    App\Modules\Campaigns\Models\ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $zoila->id)->update(['status' => 'completed', 'completed_at' => now()]);

    visit("/admin/acme/campaigns/{$campaign->id}")
        ->assertSee('Pendientes (29)')
        ->assertDontSee('Zoila Contreras')
        ->click('[aria-label="Situación"]')
        ->click('text=Contestaron (1)')
        ->assertSee('Zoila Contreras')
        ->click('[aria-label="Situación"]')
        ->click('text=Todas (30)')
        ->type('[aria-label="Buscar por nombre, correo o número"]', 'zoila')
        ->assertSee('Zoila Contreras')
        ->assertSee('Todas (30)')
        ->assertQueryStringHas('q', 'zoila')
        ->assertNoJavaScriptErrors();
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
        ->click('Reenviar a 60 personas')
        ->assertSee('el anterior deja de servir')
        ->click('Reenviar enlaces')
        ->assertSee('Enlace reenviado a 60 personas.')
        ->click('Invitar a quien falte')
        ->assertSee('Se invitó a 1 persona.')
        ->assertSee('Pendientes (61)')
        ->click('Sortear reemplazos')
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
        ->click('Cerrar evaluación')
        ->assertSee('Faltan 53 respuestas para el mínimo de la muestra que pide la norma.')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Evaluación cerrada. Los enlaces ya no funcionan.')
        ->assertSee('Cerrada')
        ->assertDontSee('Reenviar a 60 personas')
        ->assertNoJavaScriptErrors();

    expect($campaign->refresh()->status->value)->toBe('closed');
});

it('applies Guía I to the people of an event', function (bool $dark, bool $mobile): void {
    Illuminate\Support\Facades\Notification::fake();
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $people = staffOf($tenant, $plant, 3)->values();
    $people[0]->update(['name' => 'Ana López']);

    visitPage('campaigns/trauma', "/admin/acme/campaigns/trauma?work_center={$plant->id}", $dark, $mobile)
        ->assertSee('trayecto directo')
        ->assertSee('Ana López');
})->with($looks);

it('sends Guía I to the people ticked, from the NOM-035 page', function (): void {
    Illuminate\Support\Facades\Notification::fake();
    $tenant = campaignsCompany();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $people = staffOf($tenant, $plant, 3)->values();
    $people[0]->update(['name' => 'Ana López']);

    visit('/admin/acme/campaigns')
        ->click('Guía I por acontecimiento')
        ->click('#trauma-work-center')
        ->click('[role="option"]:has-text("Planta Norte")')
        ->assertSee('Ana López')
        ->click('Ana López')
        ->assertSee('(1 elegidas)')
        ->click('Enviar Guía I')
        ->assertSee('Guía I enviada a las personas elegidas.')
        ->assertSee('Guía I por acontecimiento')
        ->assertNoJavaScriptErrors();

    expect(App\Modules\Campaigns\Models\Participant::query()->withoutGlobalScopes()->sole()->employee_id)->toBe($people[0]->id);
});
