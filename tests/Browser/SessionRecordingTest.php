<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

/*
 * The anonymous, masked recording of a company's panel (resources/js/lib/session-recording.ts). What it records and
 * masks is proven in tests/js/lib/session-recording.test.ts; here, that it runs in a real panel inside its policy.
 */

beforeEach(function (): void {
    config([
        'services.posthog.project_token' => 'phc_'.str_repeat('a', 43),
        'services.posthog.session_recording' => true,
    ]);
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));
});

it('starts the recorder in a panel page with nothing outside the page policy', function (): void {
    visit('/admin/acme')
        ->assertNoJavaScriptErrors()
        // The recorder ships inside our own bundle: it registers itself without loading a script from outside.
        ->assertScript('() => new Promise((resolve) => { const ready = () => window.__PosthogExtensions__?.rrweb ? resolve(true) : setTimeout(ready, 50); ready(); })', true)
        ->assertScript(cspViolations(), '[]');
});

it('keeps Humi out of the recording, docked or in its sheet', function (bool $mobile): void {
    $page = $mobile ? visit('/admin/acme')->on()->mobile() : visit('/admin/acme');

    $page->click('[aria-label="Abrir a Humi"]')
        ->assertScript('() => new Promise((resolve) => { const shown = () => { const humi = document.querySelector("aside[aria-label=Humi], [data-slot=sheet-content]"); humi ? resolve(humi.classList.contains("ph-no-capture")) : requestAnimationFrame(shown); }; shown(); })', true);
})->with(['docked' => false, 'a phone' => true]);
