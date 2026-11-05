<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;

/**
 * A company like the demo: an evaluation in course in Oficinas Monterrey; in Planta Querétaro a draft, one closed with two
 * people to channel, and someone who teleworks and reported an accident.
 */
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
    // Its policy reached Oficinas Monterrey, so the route there is at the evaluation.
    DocumentCenter::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $offices->id]);
    $hugo = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Hugo Saiz']);
    WorkerNoticeFactory::new()->create(['tenant_id' => $tenant->id, 'teleworker_id' => TeleworkerFactory::new()->active()->create(['tenant_id' => $tenant->id, 'employee_id' => $hugo->id])->id]);

    $user = App\Models\User::factory()->create(['name' => 'Rita Ramírez']);
    TenantMembership::factory()->for($tenant)->for($user)->role(TenantRole::Hr)->create();
    test()->actingAs($user);

    return '/admin/acme';
}

// Rediseño v2 (board V2-Panel): what is urgent, where each route goes by center, the next step and the week.
it('shows the panel as the redesign draws it, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    visitPage('dashboard', panelCompany(), $dark, $mobile)
        ->assertSee('Hola, Rita')
        ->assertSee('Llevas 3 normas en 2 centros. Primero lo urgente, luego dónde vas en cada una.')
        ->assertSeeIn('[aria-labelledby="urgent"]', 'Hugo Saiz avisó un accidente de trabajo')
        ->assertSeeIn('[aria-labelledby="urgent"]', '2 personas esperan valoración clínica')
        ->assertSeeIn('[aria-labelledby="routes"] [data-norm="nom035"]', 'Oficinas Monterrey')
        ->assertSeeIn('[aria-labelledby="routes"] [data-norm="nom035"]', 'Etapa 2 de 6 · 3 de 6 contestaron, cierra en 11 días')
        ->assertSeeIn('[aria-labelledby="routes"] [data-norm="nom037"]', 'Etapa 1 de 6 · define las reglas')
        ->assertSeeIn('[aria-labelledby="routes"] [data-norm="nom037"]', 'Nadie teletrabaja en este centro')
        ->assertSeeIn('[aria-labelledby="routes"] [data-norm="seating"] li:first-child', 'Etapa 1 de 6 · identifica los puestos de pie')
        ->assertSeeIn('[aria-labelledby="next-step"]', 'Define tu política de prevención')
        ->assertSeeIn('[aria-labelledby="next-step"]', 'NOM-035 · etapa 1 de 6 · 1 centro')
        ->assertSeeIn('[aria-labelledby="week"]', 'Nada vence en los próximos 7 días.')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);

it('drops the side column below the content under 1240 px', function (): void {
    $page = visitPage('dashboard', panelCompany());
    $side = <<<'JS'
        () => {
            const routes = document.querySelector('[aria-labelledby="routes"]').getBoundingClientRect();
            const step = document.querySelector('[aria-labelledby="next-step"]').getBoundingClientRect();

            return step.left >= routes.right ? 'beside' : (step.top >= routes.bottom ? 'below' : 'overlap');
        }
        JS;

    resizeTo($page, 1440)->assertScript($side, 'beside');
    resizeTo($page, 1100)->assertScript($side, 'below');
});

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

it('keeps the page name whole in the top bar at every width, shortening a long company name first', function (): void {
    $url = panelCompany();
    Tenant::query()->where('slug', 'acme')->update(['trade_name' => 'Martínez, Aponte y Galarza e Hija']);
    $page = visitPage('dashboard', $url);
    // The crumbs that do not fit whole: the page's own always does, a company's name gives way with an ellipsis.
    $cut = '() => Array.from(document.querySelectorAll(\'nav[aria-label="Ruta"] li\')).filter((crumb) => crumb.offsetWidth > 0 && crumb.scrollWidth > crumb.clientWidth).map((crumb) => crumb.textContent).join()';

    foreach ([375 => '', 768 => 'Martínez, Aponte y Galarza e Hija', 1024 => 'Martínez, Aponte y Galarza e Hija', 1280 => ''] as $width => $shortened) {
        resizeTo($page, $width)->assertScript($cut, $shortened);
    }
});

it('fits each center of each route inside its card at every width', function (): void {
    $page = visitPage('dashboard', panelCompany());

    foreach ([375, 768, 1024, 1280] as $width) {
        // How many rows spill past their card's edge.
        resizeTo($page, $width)->assertScript('() => Array.from(document.querySelectorAll(\'[aria-labelledby="routes"] li, [aria-labelledby="urgent"] li\')).filter((row) => row.scrollWidth > row.clientWidth).length', 0);
    }
});

it('fills the width up to 1440 px and centers the page beyond it', function (bool $dark): void {
    $page = visitPage('dashboard', panelCompany(), $dark);
    $width = <<<'JS'
        () => {
            const body = document.querySelector('[data-slot="page-body"]').getBoundingClientRect();
            const frame = document.querySelector('main');
            const inner = frame.getBoundingClientRect().left + frame.clientLeft;
            const centered = Math.abs((body.left - inner) - (inner + frame.clientWidth - body.right)) <= 1;

            return `${Math.round(body.width) === frame.clientWidth ? 'full' : Math.round(body.width)} ${centered ? 'centered' : 'aside'}`;
        }
        JS;

    resizeTo($page, 1280)->assertScript($width, 'full centered');
    resizeTo($page, 2400)->assertScript($width, '1440 centered');
})->with(['light' => false, 'dark' => true]);
