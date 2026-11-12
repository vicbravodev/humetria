<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

/** Oficina, with 30 people, evaluated on 2 nov; two people of its Guía I wait to be channeled, and Rita of RH signed in. */
function evaluatedOffice(): WorkCenter
{
    test()->travelTo('2026-11-04 12:00:00');
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $office = WorkCenter::factory()->for($tenant)->create(['name' => 'Oficina']);
    staffOf($tenant, $office, 30);
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $office->id, 'closed_at' => '2026-11-02 18:00:00']);
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $closed->id, 'requires_clinical_assessment' => true]);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    return $office;
}

// Board V2-Ruta-N035: the NOM-035 route of a center after evaluating, who waits to be channeled first.
it('shows the NOM-035 route of a center, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $office = evaluatedOffice();

    visitPage('tenancy/nom035-route', "/admin/acme/campaigns/centers/{$office->id}/route", $dark, $mobile)
        ->assertSee('Tu ruta para cumplir con la NOM-035')
        ->assertSee('La evaluación de Oficina cerró el 2 nov 2026')
        ->assertSeeIn('[aria-labelledby="before"]', '2 personas esperan valoración clínica')
        ->assertSeeIn('ol[aria-label="Etapas de la NOM-035"] li:first-child', 'Aquí vas')
        ->assertSeeIn('ol[aria-label="Etapas de la NOM-035"] li:first-child', 'Escribir la política')
        ->assertSeeIn('ol[aria-label="Etapas de la NOM-035"] li:nth-child(3)', '2 personas esperan valoración clínica')
        ->assertSeeIn('[aria-labelledby="inspection"]', 'Política de prevención por escrito')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('goes from the route to the people to channel and to the inspection', function (): void {
    $office = evaluatedOffice();

    visit("/admin/acme/campaigns/centers/{$office->id}/route")
        ->click('Canalizar ahora')
        ->assertPathContains('/screenings')
        ->assertNoJavaScriptErrors();

    visit("/admin/acme/campaigns/centers/{$office->id}/route")
        ->click('Ver lo que te pediría')
        ->assertSee('Si hoy llegara una inspección a Oficina')
        ->assertNoJavaScriptErrors();
});

// Board V3-Inspeccion for NOM-035.
it('shows what you could show an inspector of NOM-035 and what is missing, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $office = evaluatedOffice();

    visitPage('tenancy/nom035-inspection', "/admin/acme/campaigns/centers/{$office->id}/inspection", $dark, $mobile)
        ->assertSee('Si hoy llegara una inspección a Oficina')
        ->assertSeeIn('[aria-labelledby="missing"] li:first-child p.font-medium', 'Política de prevención por escrito')
        ->assertSeeIn('[aria-labelledby="ready"] li:first-child p.font-medium', 'Evaluación de los factores de riesgo, vigente')
        ->assertSee('Descargar expediente')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('opens the NOM-035 dossier with only what the center can show', function (): void {
    $office = evaluatedOffice();

    visit("/admin/acme/campaigns/centers/{$office->id}/inspection")
        ->click('Solo lo que tienes')
        ->click('Abrir expediente')
        ->assertPathIs("/admin/acme/campaigns/centers/{$office->id}/dossier")
        ->assertQueryStringHas('ready', '1')
        ->assertNoJavaScriptErrors();
});

// Launch plan, decision 2: the dossier of NOM-035 with the six rows of the inspection and their evidence.
it('prints the NOM-035 dossier of a center, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $office = evaluatedOffice();

    visitPage('tenancy/nom035-dossier', "/admin/acme/campaigns/centers/{$office->id}/dossier", $dark, $mobile)
        ->assertSee('Expediente NOM-035 para la inspección')
        ->assertSee('Oficina')
        ->assertSee('Política de prevención por escrito')
        ->assertSee('Cerrada el 2 nov 2026: contestaron 0 de 0')
        ->assertSee('Imprimir o guardar PDF')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

// «Ver ruta» of NOM-035 in the panel lands on its centers (the evaluations): each one leads to its route.
it('goes from the centers of NOM-035 to the route of one', function (): void {
    $office = evaluatedOffice();

    visit('/admin/acme/campaigns')
        ->click('Ver ruta de Oficina')
        ->assertPathIs("/admin/acme/campaigns/centers/{$office->id}/route")
        ->assertSee('Tu ruta para cumplir con la NOM-035')
        ->assertNoJavaScriptErrors();
});

// Board V2-N035-Compartir: what each person will read, beside how it reaches them.
it('shows the summary each person will read before sharing the results, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $office = evaluatedOffice();
    $closed = Campaign::query()->withoutGlobalScopes()->where('work_center_id', $office->id)->sole();

    visitPage('disclosure/share-results', "/admin/acme/disclosure/results/{$closed->id}/share", $dark, $mobile)
        ->assertSee('Comparte los resultados con tu equipo')
        ->assertSeeIn('[aria-labelledby="share-preview"]', 'Resultados de la evaluación de riesgo psicosocial')
        ->assertSee('Publicar para Oficina')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('puts what each person will read under the summary below 1240 px, so the summary keeps its width', function (): void {
    $office = evaluatedOffice();
    $closed = Campaign::query()->withoutGlobalScopes()->where('work_center_id', $office->id)->sole();

    $page = visit("/admin/acme/disclosure/results/{$closed->id}/share")->assertSee('Comparte los resultados con tu equipo');

    resizeTo($page, 1100)
        ->assertScript('() => document.querySelector("h1").parentElement.getBoundingClientRect().width > 600', true);
});
