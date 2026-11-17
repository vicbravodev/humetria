<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Results\Models\ReportShare;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Str;
use Pest\Browser\Api\AwaitableWebpage;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** A closed evaluation of the company at /admin/acme, with a link to its report; returns the evaluation and the token. */
function watermarkedReport(Tenant $tenant): array
{
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => WorkCenter::factory()->for($tenant)->create()->id]);
    $token = Str::random(64);
    ReportShare::query()->withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'campaign_id' => $campaign->id,
        'token_hash' => ReportShare::hashToken($token),
        'expires_at' => now()->addHour(),
        'created_by' => memberOf($tenant, TenantRole::Owner)->id,
    ]);
    test()->actingAs(memberOf($tenant, TenantRole::Hr));

    return [$campaign, $token];
}

it('marks the reports of the demo company as DEMO, on screen and on paper', function (bool $dark, bool $mobile): void {
    [$campaign, $token] = watermarkedReport(Tenant::factory()->demo()->create(['slug' => 'acme']));

    visitPage('results/report', "/admin/acme/campaigns/{$campaign->id}/report", $dark, $mobile)
        ->assertSeeIn('[data-slot="demo-mark"]', 'DEMO')
        ->assertScript("getComputedStyle(document.querySelector('[data-slot=\"demo-mark\"]')).position", 'fixed');

    auth()->logout();
    visitPage('results/shared-report', "/report/{$token}", $dark, $mobile)
        ->assertSeeIn('[data-slot="demo-mark"]', 'DEMO');
})->with($looks);

it('shows no mark on the reports of another company', function (): void {
    Tenant::factory()->demo()->create();
    [$campaign, $token] = watermarkedReport(Tenant::factory()->create(['slug' => 'acme']));

    visit("/admin/acme/campaigns/{$campaign->id}/report")
        ->assertSee('Diagnóstico de factores de riesgo psicosocial')
        ->assertMissing('[data-slot="demo-mark"]');

    auth()->logout();
    visit("/report/{$token}")
        ->assertSee('Diagnóstico de factores de riesgo psicosocial')
        ->assertMissing('[data-slot="demo-mark"]');
});

/**
 * The fixed pieces of the screen the mark must leave clear, by selector: those on the page, the ones it overlaps.
 * The banner and the top bar must be there: the check means nothing without them.
 */
function markOverlaps(): string
{
    return <<<'JS'
        () => {
            const box = (element) => element.getBoundingClientRect();
            const mark = box(document.querySelector('[data-slot="demo-mark"]'));
            const required = ['[data-test="impersonation-banner"]', '[data-slot="app-topbar"]'];
            const optional = ['nav[aria-label="Menú principal"]', 'button[aria-label^="Primeros pasos"]', '[data-slot="sidebar-footer"]'];

            return [...required, ...optional].flatMap((selector) => {
                const element = document.querySelector(selector);

                if (element === null) {
                    return required.includes(selector) ? [`${selector} missing`] : [];
                }

                const other = box(element);
                const overlaps = other.width > 0 && mark.left < other.right && other.left < mark.right && mark.top < other.bottom && other.top < mark.bottom;

                return overlaps ? [selector] : [];
            });
        }
        JS;
}

it('keeps the mark clear of the impersonation banner and the top bar of the demo company', function (bool $dark, bool $mobile): void {
    $this->actingAs(superadmin());
    $demo = Tenant::factory()->demo()->create(['trade_name' => 'Manufacturas Alba del Bajío', 'slug' => 'alba-demo']);
    TenantMembership::factory()->for($demo)->for(User::factory()->create(['name' => 'Ana Pérez']))->create();

    visitPage('dashboard', '/admin/demo', $dark, $mobile, fn (AwaitableWebpage $page) => $page
        ->click('Entrar como dueño')
        ->assertScript(pathBecomes('/admin/alba-demo'), true))
        ->assertSee('Estás viendo Humetría como Ana Pérez')
        ->assertScript(markOverlaps(), []);
})->with($looks);

it('prints the mark as a large, faint diagonal watermark on every sheet, not as the tab of the screen', function (bool $dark): void {
    [$campaign] = watermarkedReport(Tenant::factory()->demo()->create(['slug' => 'acme']));

    $page = visitPage('results/report', "/admin/acme/campaigns/{$campaign->id}/report", $dark);
    emulatePrint($page)
        ->assertScript(<<<'JS'
            () => {
                const mark = document.querySelector('[data-slot="demo-mark"]');
                const style = getComputedStyle(mark);
                const box = mark.getBoundingClientRect();
                const centered = Math.abs(box.left + box.width / 2 - innerWidth / 2) < 2 && Math.abs(box.top + box.height / 2 - innerHeight / 2) < 2;

                return {
                    // Fixed: the printer repeats it on each sheet.
                    position: style.position,
                    faint: Number(style.opacity) < 0.2,
                    diagonal: style.rotate !== 'none' && style.rotate !== '0deg',
                    large: parseFloat(style.fontSize) >= 96,
                    transparent: style.backgroundColor === 'rgba(0, 0, 0, 0)' && style.borderTopWidth === '0px',
                    centered,
                    pointerEvents: style.pointerEvents,
                };
            }
            JS, ['position' => 'fixed', 'faint' => true, 'diagonal' => true, 'large' => true, 'transparent' => true, 'centered' => true, 'pointerEvents' => 'none']);
})->with(['light' => false, 'dark' => true]);
