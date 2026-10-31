<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\WorkCenter;

/** A company like the demo: an evaluation in course, a draft ready to launch and one closed with its scores. */
function panelCompany(): string
{
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $offices = WorkCenter::factory()->for($tenant)->create(['name' => 'Oficinas Monterrey']);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Querétaro']);
    $active = Campaign::factory()->active(CampaignClock::today()->addDays(11)->toDateString())->create(['tenant_id' => $tenant->id, 'work_center_id' => $offices->id, 'instruments' => ['nom035.guide-i', 'nom035.guide-iii'], 'workers' => 60]);
    Campaign::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

    foreach ([[$active, 'nom035.guide-iii', InstrumentStatus::Completed], [$active, 'nom035.guide-iii', InstrumentStatus::Pending], [$closed, 'nom035.guide-ii', InstrumentStatus::Completed]] as [$campaign, $instrument, $status]) {
        ParticipantInstrument::factory()->count(3)->create([
            'participant_id' => fn () => Participant::factory()->create(['campaign_id' => $campaign->id])->id,
            'instrument' => $instrument,
            'status' => $status,
            'completed_at' => $status === InstrumentStatus::Completed ? now() : null,
        ]);
    }

    AnonymousResponse::factory()->count(5)->create(['campaign_id' => $closed->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', 'sometimes')]);
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $closed->id, 'requires_clinical_assessment' => true]);

    $user = App\Models\User::factory()->create(['name' => 'Rita Ramírez']);
    TenantMembership::factory()->for($tenant)->for($user)->role(TenantRole::Hr)->create();
    test()->actingAs($user);

    return '/admin/acme';
}

it('shows the panel as the redesign draws it, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    visitPage('dashboard', panelCompany(), $dark, $mobile)
        ->assertSee('Hola, Rita')
        ->assertSee('Tienes una evaluación en curso y un borrador listo para lanzar.')
        ->assertSeeIn('[aria-labelledby="active-evaluation"]', 'Oficinas Monterrey')
        ->assertSeeIn('[aria-labelledby="active-evaluation"]', '11 días')
        ->assertSeeIn('[aria-labelledby="next-steps"]', 'Lanza la evaluación de Planta Querétaro')
        ->assertSeeIn('[aria-labelledby="next-steps"]', 'Completa el informe 7.7 de Planta Querétaro')
        ->assertSeeIn('[aria-labelledby="next-steps"]', '2 personas requieren valoración clínica')
        ->assertSeeIn('[aria-labelledby="cycle"]', 'Al día')
        ->assertSeeIn('[aria-labelledby="closed"]', 'en riesgo alto o muy alto')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('counts the evaluations in course in the sidebar and tells when the next one closes, until it folds', function (): void {
    visitPage('dashboard', panelCompany())
        ->assertSeeIn('[data-sidebar="menu-badge"]', '1')
        ->assertSeeIn('[data-test="next-obligation"]', 'La próxima evaluación cierra en 11 días')
        ->click('[data-sidebar="trigger"]')
        ->assertScript('() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => true)')
        ->assertScript('() => getComputedStyle(document.querySelector(\'[data-test="next-obligation"]\')).display', 'none');
});

it('shows no next obligation without an evaluation in course', function (): void {
    visitPage('dashboard', signInToPanel())->assertMissing('[data-test="next-obligation"]');
});

it('starts an action from the ⌘K palette, grouped under its heading', function (): void {
    visitPage('dashboard', panelCompany())
        ->assertScript('() => { window.dispatchEvent(new KeyboardEvent("keydown", { key: "k", metaKey: true })); return true; }')
        ->assertSeeIn('[role="listbox"] [role="group"]', 'Acciones')
        ->type('#command-search', 'csv')
        ->assertScript('() => document.querySelector(\'[role="option"][aria-selected="true"] [data-slot="command-label"]\')?.textContent', 'Cargar empleados desde CSV')
        ->assertNoAccessibilityIssues(1)
        ->keys('#command-search', 'Enter')
        ->assertPathIs('/admin/acme/employees/import');
});

it('reminds whoever is missing from the panel', function (): void {
    visitPage('dashboard', panelCompany())
        ->click('Recordar a quien falta')
        ->assertSee('Enlace reenviado a 3 personas.');
});

/** Where the arc of the evaluation in course sits in its card: centered or not, and its figures beside it or below. */
function activeCardLayout(): string
{
    return <<<'JS'
        () => {
            const card = document.querySelector('[aria-labelledby="active-evaluation"]').getBoundingClientRect();
            const meter = document.querySelector('[aria-labelledby="active-evaluation"] [data-slot="arc-meter"]').getBoundingClientRect();
            const kpis = document.querySelector('[data-test="active-kpis"]').getBoundingClientRect();
            const centered = Math.abs((meter.left + meter.right) / 2 - (card.left + card.right) / 2) <= 2;

            return `${centered ? 'centered' : 'aside'} ${kpis.top < meter.bottom && kpis.left >= meter.right ? 'beside' : 'below'}`;
        }
        JS;
}

it('centers the arc over its figures on a phone and sets them beside it on a desktop', function (bool $dark): void {
    visitPage('dashboard', panelCompany(), $dark)
        ->resize(375, 800)
        ->assertScript(activeCardLayout(), 'centered below')
        ->resize(768, 900)
        ->assertScript(activeCardLayout(), 'centered below')
        ->resize(1280, 900)
        ->assertScript(activeCardLayout(), 'aside beside');
})->with(['light' => false, 'dark' => true]);
