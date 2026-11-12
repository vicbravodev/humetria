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
use App\Modules\Seating\Models\SeatingProfile;
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
    // Planta Querétaro said that someone works standing; Oficinas Monterrey has not answered «¿Te aplica?» yet.
    SeatingProfile::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
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
        // Rediseño v2 (decisión A2): where nobody teleworks or works standing yet, the center answers «¿Te aplica?» first.
        ->assertSeeIn('[aria-labelledby="routes"] [data-norm="nom037"]', 'Contesta si te aplica')
        ->assertSeeIn('[aria-labelledby="routes"] [data-norm="seating"] li:first-child', 'Falta contestar')
        ->assertSeeIn('[aria-labelledby="routes"] [data-norm="seating"] li:last-child', 'Etapa 1 de 6 · identifica los puestos de pie')
        // Board V3-Onboarding: before any stage, the questions of the center that has not answered «¿Te aplica?».
        ->assertSeeIn('[aria-labelledby="next-step"]', 'Dos preguntas por centro y armamos todo')
        ->assertSeeIn('[aria-labelledby="next-step"]', '1 centro sin contestar')
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
    // NOM-035's count shows on its family while the family is folded.
    visitPage('dashboard', panelCompany())
        ->assertSeeIn('[data-sidebar="group-badge"]', '1')
        ->assertSeeIn('[data-test="next-obligation"]', 'La próxima evaluación cierra en 11 días')
        ->click('[data-sidebar="trigger"]')
        ->assertScript('() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => true)')
        ->assertScript('() => getComputedStyle(document.querySelector(\'[data-test="next-obligation"]\')).display', 'none');
});

it('scrolls the sidebar on a short screen without a scrollbar, fading the edge that has more', function (): void {
    $page = visitPage('dashboard', panelCompany());
    $nav = 'document.querySelector(\'[data-sidebar="content"]\')';
    $state = "() => [getComputedStyle({$nav}).scrollbarWidth, {$nav}.hasAttribute('data-more-above'), {$nav}.hasAttribute('data-more-below')].join(' ')";

    // Every family open, so the list is longer than the screen.
    $page->click('Cumple la norma')->click('Escucha a tu gente')->click('Ordena tu operación');

    resizeTo($page, 1440, 560)->assertScript("() => {$nav}.scrollHeight > {$nav}.clientHeight", true)
        ->assertScript($state, 'none false true');
    $page->assertScript("() => { {$nav}.scrollTop = {$nav}.scrollHeight; return new Promise((resolve) => requestAnimationFrame(() => resolve(true))); }", true)
        ->assertScript($state, 'none true false');
    resizeTo($page, 1440, 1400)->assertScript($state, 'none false false');
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

// Board 40 (V4-Menu-Roles): on a phone the menu becomes a bar at the bottom with Panel, Normas, Quejas and Más.
it('folds the menu into a bottom bar on a phone, role-aware like the sidebar', function (): void {
    $page = visit(panelCompany())->on()->mobile();

    $page->assertSeeIn('nav[aria-label="Menú principal"]', 'Panel')
        ->assertSeeIn('nav[aria-label="Menú principal"]', 'Quejas')
        ->click('Normas')
        ->click('[role="menu"] a[href$="/seating"]')
        ->assertPathIs('/admin/acme/seating')
        ->click('Más')
        ->assertSeeIn('[data-mobile="true"]', 'Centros de trabajo')
        ->assertNoJavaScriptErrors();
});

it('groups the sidebar by the families of tools the landing offers', function (): void {
    visit(panelCompany())
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="group-label"]\')].map((label) => label.querySelector("span").textContent).join(" · ")', 'Cumple la norma · Escucha a tu gente · Ordena tu operación')
        // Every family's name reads whole, with its count beside it.
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="group-label"] span:first-of-type\')].every((name) => name.scrollWidth <= name.clientWidth)', true)
        // Each family is told by its icon too, besides the chevron that folds it.
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="group-label"]\')].map((label) => label.querySelectorAll("svg").length).join(" ")', '2 2 2')
        ->click('Cumple la norma')
        ->assertSeeIn('[data-sidebar="sidebar"]', 'Ley Silla')
        ->assertNoJavaScriptErrors();
});

it('folds every family of tools until it is opened, and opens on arriving the one of the page', function (): void {
    $sidebar = 'document.querySelector(\'[data-sidebar="sidebar"]\')';
    $page = visit(panelCompany())
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="group-label"]\')].map((label) => label.getAttribute("aria-expanded")).join(" ")', 'false false false')
        ->assertScript("() => {$sidebar}.innerText.includes('Ley Silla')", false)
        ->click('Cumple la norma')
        // It unfolds, it does not pop: its tools grow in with the design system's ease-out.
        ->assertScript('() => document.querySelector(\'[data-sidebar="group"] [data-slot="collapsible-content"][data-state="open"]\').getAnimations().some((animation) => animation.animationName === "collapsible-down")', true)
        ->assertScript("() => {$sidebar}.innerText.includes('Ley Silla')", true)
        // The family is the parent: as big as its tools and bolder, and its tools hang indented under it.
        ->assertScript('() => { const family = document.querySelector(\'[data-sidebar="group-label"][aria-expanded="true"]\'); const tool = family.closest(\'[data-sidebar="group"]\').querySelector(\'[data-sidebar="menu-button"]\'); const [f, t] = [getComputedStyle(family), getComputedStyle(tool)]; return parseFloat(f.fontSize) >= parseFloat(t.fontSize) && Number(f.fontWeight) > Number(t.fontWeight) && tool.getBoundingClientRect().left > family.getBoundingClientRect().left; }', true)
        // Open, the count goes back to NOM-035 itself.
        ->assertMissing('[data-sidebar="group-badge"]')
        ->assertSeeIn('[data-sidebar="menu-badge"]', '1')
        ->click('Cumple la norma')
        ->assertScript("() => {$sidebar}.innerText.includes('Ley Silla')", false);

    $page->navigate('/admin/acme/seating')
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="group-label"]\')].map((label) => label.getAttribute("aria-expanded")).join(" ")', 'true false false')
        ->assertScript("() => {$sidebar}.innerText.includes('Ley Silla')", true)
        ->assertNoJavaScriptErrors();
});

it('keeps the bottom bar off a wide screen', function (): void {
    visit(panelCompany())->assertScript('() => getComputedStyle(document.querySelector(\'nav[aria-label="Menú principal"]\')).display', 'none');
});
