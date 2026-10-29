<?php

declare(strict_types=1);

use App\Models\User;
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
    assertLogoInsideItsBox(visitPage('auth/login', '/admin/login', $dark, $mobile));
})->with($looks);

it('renders the dashboard accessibly with the logo in the sidebar', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('dashboard', '/admin', $dark, $mobile, reach: function (AwaitableWebpage $page) use ($mobile): void {
        if ($mobile) {
            // On mobile the sidebar is an off-canvas sheet: open it and let it finish sliding in.
            $page->click('[data-sidebar="trigger"]')
                ->assertScript('() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => document.querySelector(\'[data-mobile="true"][data-state="open"]\') !== null)');
        }
    })->assertVisible(VISIBLE_LOGO);
})->with($looks);

it('gives the mobile menu button a 44 px touch target', function (bool $dark): void {
    $this->actingAs(User::factory()->create());

    visitPage('dashboard', '/admin', $dark, true)
        ->assertScript('() => { const box = document.querySelector(\'[data-sidebar="trigger"]\').getBoundingClientRect(); return box.width >= 44 && box.height >= 44; }');
})->with(['light' => false, 'dark' => true]);

// Lets every running transition settle, so what the next assertion reads is the resting state.
const SETTLED = '() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => true)';

it('frames the app as Sidebar.md and PanelRH ask, not as the starter kit', function (bool $dark): void {
    $this->actingAs(User::factory()->create());

    visitPage('dashboard', '/admin', $dark)
        // The page sits in a raised panel beside the sidebar, with the button that folds the sidebar at its top.
        ->assertScript('() => document.querySelector(\'[data-slot="sidebar"][data-variant="inset"]\') !== null')
        ->assertScript('() => getComputedStyle(document.querySelector(\'[data-slot="sidebar-inset"]\')).boxShadow !== "none"')
        ->assertScript('() => document.querySelector(\'[data-sidebar="trigger"]\').offsetParent !== null')
        ->assertDontSee('Repositorio')
        ->assertDontSee('Documentación')
        // Group label in caption style; items of 44 px in body-strong with 20 px icons.
        ->assertScript('() => { const label = getComputedStyle(document.querySelector(\'[data-sidebar="group-label"]\')); return label.textTransform === "uppercase" && label.fontSize === "13px"; }')
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="menu-button"]\')].every((button) => button.getBoundingClientRect().height >= 44 && getComputedStyle(button).fontSize === "16px")')
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="menu-button"] svg\')].every((icon) => icon.getBoundingClientRect().width === 20)')
        // Active item: 600 and its icon in sidebar-primary.
        ->assertScript('() => { const active = document.querySelector(\'[data-sidebar="menu-button"][data-active="true"]\'); const probe = document.createElement("span"); probe.style.color = "var(--sidebar-primary)"; document.body.append(probe); const primary = getComputedStyle(probe).color; probe.remove(); return getComputedStyle(active).fontWeight === "600" && getComputedStyle(active.querySelector("svg")).color === primary; }')
        // Page title in h1 (30/38).
        ->assertScript('() => getComputedStyle(document.querySelector("main h1")).fontSize', '30px');
})->with(['light' => false, 'dark' => true]);

it('folds the sidebar to its icons and remembers it', function (bool $dark): void {
    $this->actingAs(superadmin());

    $page = visitPage('dashboard', '/admin', $dark)
        ->click('[data-sidebar="trigger"]')
        ->assertScript(SETTLED)
        ->assertScript('() => document.querySelector(\'[data-slot="sidebar"]\').dataset.collapsible', 'icon')
        // Each item keeps a 44 px square target and only its icon shows.
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="menu-button"]\')].every((button) => { const box = button.getBoundingClientRect(); return box.width >= 44 && box.width < 56 && box.height >= 44; })')
        ->assertScript('() => document.querySelector(\'[data-sidebar="group-label"]\').getBoundingClientRect().height === 0 || getComputedStyle(document.querySelector(\'[data-sidebar="group-label"]\')).opacity === "0"')
        // Its name comes back as a tooltip.
        ->hover('[data-sidebar="menu-button"][href$="/admin/companies"]')
        ->assertScript('() => document.querySelector(\'[role="tooltip"]\')?.textContent', 'Empresas')
        ->assertNoAccessibilityIssues(1);

    $page->refresh()
        ->assertScript('() => document.querySelector(\'[data-slot="sidebar"]\').dataset.state', 'collapsed')
        // ⌘B (Ctrl+B) unfolds it again.
        ->assertScript('() => { window.dispatchEvent(new KeyboardEvent("keydown", { key: "b", metaKey: true })); return true; }')
        ->assertScript(SETTLED)
        ->assertScript('() => document.querySelector(\'[data-slot="sidebar"]\').dataset.state', 'expanded');
})->with(['light' => false, 'dark' => true]);

it('switches the theme from the app frame and keeps it', function (bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('dashboard', '/admin', mobile: $mobile)
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
    $this->actingAs(superadmin());

    visitPage('dashboard', '/admin')
        // Pressables answer with the strong ease-out curve.
        ->assertScript('() => getComputedStyle(document.querySelector(\'[data-sidebar="trigger"]\')).transitionTimingFunction.includes("cubic-bezier(0.23, 1, 0.32, 1)")')
        // Moving to another page cross-fades it through the View Transitions API.
        ->assertScript('() => { window.transitions = 0; const start = document.startViewTransition.bind(document); document.startViewTransition = (update) => { window.transitions++; return start(update); }; return true; }')
        ->click('Empresas')
        ->assertPathIs('/admin/companies')
        ->assertScript('() => window.transitions', 1);
});
