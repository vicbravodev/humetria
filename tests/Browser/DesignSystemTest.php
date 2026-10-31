<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Pest\Browser\Api\AwaitableWebpage;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

// The logo renders a light and a dark <img>; only the one matching the theme is visible.
const VISIBLE_LOGO = 'img[alt="Humetría"]:visible';

function assertLogoInsideItsBox(AwaitableWebpage $page): void
{
    $page->assertVisible(VISIBLE_LOGO)
        ->assertScript('() => [...document.querySelectorAll(\'img[alt="Humetría"]\')].filter((img) => img.offsetParent !== null).every((img) => { const logo = img.getBoundingClientRect(); const box = img.parentElement.getBoundingClientRect(); return logo.left >= box.left && logo.right <= box.right && logo.top >= box.top && logo.bottom <= box.bottom; })');
}

it('renders the login page accessibly', function (bool $dark, bool $mobile): void {
    assertLogoInsideItsBox(visitPage('auth/login', '/admin/login', $dark, $mobile)
        // The title reads as one, as the Login screen draws it: 28/36 in 600.
        ->assertScript('() => { const title = getComputedStyle(document.querySelector("h1")); return title.fontSize === "28px" && title.lineHeight === "36px" && title.fontWeight === "600"; }'));
})->with($looks);

it('renders the dashboard accessibly with the logo in the sidebar', function (bool $dark, bool $mobile): void {
    $panel = signInToPanel();

    visitPage('dashboard', $panel, $dark, $mobile, reach: function (AwaitableWebpage $page) use ($mobile): void {
        if ($mobile) {
            // On mobile the sidebar is an off-canvas sheet: open it and let it finish sliding in.
            $page->click('[data-sidebar="trigger"]')
                ->assertScript('() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => document.querySelector(\'[data-mobile="true"][data-state="open"]\') !== null)');
        }
    })->assertVisible(VISIBLE_LOGO);
})->with($looks);

it('gives the mobile menu button a 44 px touch target', function (bool $dark): void {
    $panel = signInToPanel();

    visitPage('dashboard', $panel, $dark, true)
        ->assertScript('() => { const box = document.querySelector(\'[data-sidebar="trigger"]\').getBoundingClientRect(); return box.width >= 44 && box.height >= 44; }');
})->with(['light' => false, 'dark' => true]);

// Lets every running transition settle, so what the next assertion reads is the resting state.
const SETTLED = '() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => true)';

it('frames the app as Sidebar.md and PanelRH ask, not as the starter kit', function (bool $dark): void {
    $panel = signInToPanel(superadmin());

    visitPage('dashboard', $panel, $dark)
        // The page sits in a raised panel beside the sidebar, with the button that folds the sidebar at its top.
        ->assertScript('() => document.querySelector(\'[data-slot="sidebar"][data-variant="inset"]\') !== null')
        ->assertScript('() => getComputedStyle(document.querySelector(\'[data-slot="sidebar-inset"]\')).boxShadow !== "none"')
        ->assertScript('() => document.querySelector(\'[data-sidebar="trigger"]\').offsetParent !== null')
        ->assertDontSee('Repositorio')
        ->assertDontSee('Documentación')
        // Group label (Plataforma, the only one left to a superadmin) in sentence case at 13 px (pantallas: Panel); items of 44 px at 16 px with 20 px icons.
        ->assertScript('() => { const label = getComputedStyle(document.querySelector(\'[data-sidebar="group-label"]\')); return label.textTransform === "none" && label.fontSize === "13px"; }')
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="menu-button"]\')].every((button) => button.getBoundingClientRect().height >= 44 && getComputedStyle(button).fontSize === "16px")')
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="menu-button"] svg\')].every((icon) => icon.getBoundingClientRect().width === 20)')
        // Active item: a card (card fill, 500) with its icon in sidebar-primary (pantallas: Panel).
        ->assertScript('() => { const active = document.querySelector(\'[data-sidebar="menu-button"][data-active="true"]\'); const probe = document.createElement("span"); probe.style.color = "var(--sidebar-primary)"; probe.style.backgroundColor = "var(--card)"; document.body.append(probe); const primary = getComputedStyle(probe).color; const card = getComputedStyle(probe).backgroundColor; probe.remove(); return getComputedStyle(active).fontWeight === "500" && getComputedStyle(active).backgroundColor === card && getComputedStyle(active.querySelector("svg")).color === primary; }')
        // Page title in h1 (30/38).
        ->assertScript('() => getComputedStyle(document.querySelector("main h1")).fontSize', '30px');
})->with(['light' => false, 'dark' => true]);

it('folds the sidebar to its icons and remembers it', function (bool $dark): void {
    $panel = signInToPanel(superadmin());

    $page = visitPage('dashboard', $panel, $dark)
        ->click('[data-sidebar="trigger"]')
        ->assertScript(SETTLED)
        ->assertScript('() => document.querySelector(\'[data-slot="sidebar"]\').dataset.collapsible', 'icon')
        // Each item keeps a 44 px square target and only its icon shows.
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="menu-button"]\')].every((button) => { const box = button.getBoundingClientRect(); return box.width >= 44 && box.width < 56 && box.height >= 44; })')
        ->assertScript('() => document.querySelector(\'[data-sidebar="group-label"]\').getBoundingClientRect().height === 0 || getComputedStyle(document.querySelector(\'[data-sidebar="group-label"]\')).opacity === "0"')
        // Its name comes back as a tooltip.
        ->hover('[data-sidebar="menu-button"][href$="/admin/companies"]')
        ->assertScript('() => document.querySelector(\'[role="tooltip"]\')?.textContent', 'Empresas')
        // axe reads colors as painted: let the tooltip finish fading in before it judges the contrast.
        ->assertScript(SETTLED)
        ->assertNoAccessibilityIssues(1);

    $page->refresh()
        ->assertScript('() => document.querySelector(\'[data-slot="sidebar"]\').dataset.state', 'collapsed')
        // ⌘B (Ctrl+B) unfolds it again.
        ->assertScript('() => { window.dispatchEvent(new KeyboardEvent("keydown", { key: "b", metaKey: true })); return true; }')
        ->assertScript(SETTLED)
        ->assertScript('() => document.querySelector(\'[data-slot="sidebar"]\').dataset.state', 'expanded');
})->with(['light' => false, 'dark' => true]);

it('keeps the top bar in view while the page scrolls', function (bool $mobile): void {
    $this->actingAs(superadmin());
    Tenant::factory()->count(15)->create();

    visitPage('tenancy/index', '/admin/companies', mobile: $mobile)
        ->assertScript('() => { window.scrollTo(0, 600); return new Promise((resolve) => requestAnimationFrame(() => resolve(window.scrollY > 0))); }')
        ->assertScript('() => { const bar = document.querySelector(\'[data-slot="app-topbar"]\').getBoundingClientRect(); return bar.top === 0 && bar.height > 0; }')
        ->assertScript('() => document.elementFromPoint(window.innerWidth - 30, 20)?.closest(\'[data-slot="app-topbar"]\') !== null');
})->with(['desktop' => false, 'mobile' => true]);

// Opens the command palette the way the keyboard does.
const OPEN_PALETTE = '() => { window.dispatchEvent(new KeyboardEvent("keydown", { key: "k", metaKey: true })); return true; }';

it('jumps to a page or switches the theme from the ⌘K palette', function (): void {
    $panel = signInToPanel(superadmin());

    visitPage('dashboard', $panel, reach: fn (AwaitableWebpage $page) => $page
        ->assertScript(OPEN_PALETTE)
        ->assertVisible('#command-search'))
        ->type('#command-search', 'empr')
        ->assertScript('() => document.querySelector(\'[role="option"][aria-selected="true"] [data-slot="command-label"]\')?.textContent', 'Empresas')
        ->keys('#command-search', 'Enter')
        ->assertPathIs('/admin/companies')
        ->assertMissing('#command-search')
        // The same palette opens from the button in the top bar.
        ->click('@command-palette')
        ->type('#command-search', 'oscuro')
        ->keys('#command-search', 'Enter')
        ->assertScript('() => document.documentElement.classList.contains("dark")')
        ->click('@command-palette')
        ->type('#command-search', 'xyz')
        ->assertSee('Sin resultados');
});

it('offers the console pages in the palette only to the superadmin', function (): void {
    $this->actingAs(User::factory()->create());

    visit('/admin')
        ->assertScript(OPEN_PALETTE)
        ->type('#command-search', 'empresas')
        ->assertSee('Sin resultados');
});

it('greets an empty list with an icon and what comes next', function (string $url, string $component, string $title): void {
    $this->actingAs(superadmin());

    visitPage($component, $url)
        ->assertSeeIn('[data-slot="empty-state"] h2', $title)
        ->assertPresent('[data-slot="empty-state"] svg');
})->with([
    ['/admin/companies', 'tenancy/index', 'Aún no hay empresas'],
    ['/admin/demo-requests', 'demos/index', 'Aún no hay solicitudes'],
]);

it('greets a company without campaigns with an icon and what comes next', function (): void {
    $panel = signInToPanel(superadmin());
    // With a work center already: without one, the panel points there first (see WorkCentersTest).
    WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->sole())->create();

    visitPage('dashboard', $panel)
        ->assertSeeIn('[data-slot="empty-state"] h2', 'Aún no hay campañas')
        ->assertPresent('[data-slot="empty-state"] svg');
});

it('titles the settings like every other page and marks where you are', function (bool $dark): void {
    $this->actingAs(User::factory()->create());

    visitPage('settings/profile', '/admin/settings/profile', $dark)
        ->assertScript('() => getComputedStyle(document.querySelector("main h1")).fontSize', '30px')
        ->assertScript('() => document.querySelector(\'nav[aria-label="Configuración"] [aria-current="page"]\')?.textContent', 'Perfil')
        // Deleting the account sits in an outlined zone, not on a red block.
        ->assertScript('() => { const zone = getComputedStyle(document.querySelector(\'[data-slot="danger-zone"]\')); const probe = document.createElement("span"); probe.style.color = "var(--card)"; document.body.append(probe); const card = getComputedStyle(probe).color; probe.remove(); return zone.backgroundColor === card; }');
})->with(['light' => false, 'dark' => true]);

it('switches the theme from the app frame and keeps it', function (bool $mobile): void {
    $panel = signInToPanel();

    visitPage('dashboard', $panel, mobile: $mobile)
        ->assertScript('() => document.querySelector(\'[role="radiogroup"][aria-label="Tema"]\').offsetParent !== null')
        ->click('[role="radio"][aria-label="Oscuro"]')
        ->assertScript('() => document.documentElement.classList.contains("dark")')
        ->assertScript('() => document.querySelector(\'[role="radio"][aria-label="Oscuro"]\').getAttribute("aria-checked")', 'true')
        ->refresh()
        ->assertScript('() => document.documentElement.classList.contains("dark")')
        ->click('[role="radio"][aria-label="Claro"]')
        ->assertScript('() => document.documentElement.classList.contains("dark")', false);
})->with(['desktop' => false, 'mobile' => true]);

it('moves with the design system motion', function (): void {
    $panel = signInToPanel(superadmin());

    visitPage('dashboard', $panel)
        // Pressables answer with the strong ease-out curve.
        ->assertScript('() => getComputedStyle(document.querySelector(\'[data-sidebar="trigger"]\')).transitionTimingFunction.includes("cubic-bezier(0.23, 1, 0.32, 1)")')
        // Moving to another page cross-fades it through the View Transitions API.
        ->assertScript('() => { window.transitions = 0; const start = document.startViewTransition.bind(document); document.startViewTransition = (update) => { window.transitions++; return start(update); }; return true; }')
        ->click('Empresas')
        ->assertPathIs('/admin/companies')
        ->assertScript('() => window.transitions', 1);
});

it('labels no sidebar group after a single tool', function (): void {
    $this->actingAs(User::factory()->create());

    visit('/admin')
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="group-label"]\')].every((label) => !label.textContent.includes("NOM-035"))')
        ->assertDontSeeIn('[data-sidebar="sidebar"]', 'Cumplimiento');
});
